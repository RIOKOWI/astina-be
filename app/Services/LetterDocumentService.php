<?php

namespace App\Services;

use App\Models\Letter;
use App\Models\LetterDocument;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LetterDocumentService
{
    public function generateForLetter(Letter $letter): LetterDocument
    {
        $templateKey = $this->resolveTemplateKey($letter);
        $signaturePath = config('letters.default_signature');

        $this->ensureTemplateExists($templateKey);
        $this->ensureSignatureExists($signaturePath);

        $tempDir = $this->extractTemplate($templateKey);

        try {
            $this->replacePlaceholders($tempDir, $letter);
            $this->addSignatureImage($tempDir, $signaturePath, $letter);
            $docxPath = $this->packDocx($tempDir, $letter);
            $storedPath = $this->storeDocument($docxPath, $letter);
            $document = $this->createLetterDocument($letter, $storedPath);
        } finally {
            $this->cleanup($tempDir);
        }

        return $document;
    }

    private function resolveTemplateKey(Letter $letter): string
    {
        // Map letter_type code to template key, or fall back to surat_pengantar
        $code = $letter->letterType->code ?? 'SKD';

        return match (strtolower($code)) {
            default => config('letters.templates.surat_pengantar'),
        };
    }

    private function ensureTemplateExists(string $path): void
    {
        if (! Storage::disk('private')->exists($path)) {
            Log::error('Letter template not found', [
                'letter_type_id' => null,
                'template_path' => $path,
                'exception_class' => 'TemplateNotFoundException',
            ]);
            abort(500, 'Template tidak ditemukan.');
        }
    }

    private function ensureSignatureExists(string $path): void
    {
        if (! Storage::disk('private')->exists($path)) {
            Log::error('Signature image not found', [
                'signature_path' => $path,
                'exception_class' => 'SignatureNotFoundException',
            ]);
            abort(500, 'Gambar tanda tangan tidak ditemukan.');
        }
    }

    private function extractTemplate(string $templatePath): string
    {
        $tempDir = sys_get_temp_dir().'/letter_'.Str::uuid()->toString();
        mkdir($tempDir, 0755, true);

        $templateContent = Storage::disk('private')->readStream($templatePath);
        $tmpFile = $tempDir.'/template.docx';
        $out = fopen($tmpFile, 'wb');
        stream_copy_to_stream($templateContent, $out);
        fclose($out);
        fclose($templateContent);

        $zip = new \ZipArchive;
        $zip->open($tmpFile);
        $zip->extractTo($tempDir);
        $zip->close();

        unlink($tmpFile);

        return $tempDir;
    }

    private function replacePlaceholders(string $tempDir, Letter $letter): void
    {
        $docXmlPath = $tempDir.'/word/document.xml';
        $xmlContent = file_get_contents($docXmlPath);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        // Suppress warnings for malformed XML entities we handle manually
        libxml_use_internal_errors(true);
        $dom->loadXML($xmlContent, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOCDATA);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        if (! empty($errors)) {
            Log::error('DOMDocument XML parse errors', [
                'letter_id' => $letter->id,
                'errors' => array_map(fn ($e) => $e->message, $errors),
            ]);
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $resident = $letter->resident;
        $fieldValues = $letter->fieldValues->keyBy(fn ($fv) => $fv->letterField->field_key);

        // Mapping: label text -> replacement value
        $replacements = $this->buildReplacements($resident, $fieldValues, $letter);

        // Find all table rows
        $rows = $xpath->query('//w:tr');

        foreach ($rows as $row) {
            $cells = $xpath->query('w:tc', $row);

            if ($cells->length < 2) {
                continue;
            }

            // Check first cell for label text
            $labelCell = $cells->item(0);
            $labelText = $this->getCellText($xpath, $labelCell);

            // Also check if first cell contains dots (standalone row with dots only)
            $hasDots = $this->cellContainsDots($xpath, $labelCell);

            if ($hasDots && $cells->length >= 2) {
                // This is a standalone dots row (like "keperluan" purpose field)
                $this->replaceDotsInCell($xpath, $labelCell, '');
                $valueCell = $cells->item(1);
                if ($valueCell) {
                    $purpose = $this->getPurposeText($fieldValues, $letter);
                    $this->replaceDotsInCell($xpath, $valueCell, $purpose);
                }

                continue;
            }

            // Check if this row matches a known label
            $matchedLabel = null;
            foreach (array_keys($replacements) as $label) {
                if ($this->textContains($labelText, $label)) {
                    $matchedLabel = $label;
                    break;
                }
            }

            if ($matchedLabel && isset($replacements[$matchedLabel]) && $cells->length >= 3) {
                $valueCell = $cells->item(2); // 3rd column is the value
                if ($valueCell) {
                    $value = $replacements[$matchedLabel];
                    $this->replaceCellContent($xpath, $valueCell, $value);
                    unset($replacements[$matchedLabel]);
                }
            }
        }

        // Handle "Alamat Sekarang" cell which has multi-paragraph static content
        // Replace any remaining dots in the document
        $body = $xpath->query('//w:body')->item(0);
        if ($body) {
            $bodyXml = $dom->saveXML($body);
            // Replace all dot sequences with empty or the actual address
            $address = $replacements['Alamat Sekarang'] ?? $resident->address ?? '';

            // Replace address-related dots in the document
            // The address cell has: "Perum Kutabumi 7 Astina Blok B" etc + dots
            // We replace the dots with the actual address
            $bodyXml = preg_replace(
                '/(<w:p[^>]*>.*?Perum Kutabumi.*?<\/w:p>)/s',
                $this->makeAddressParagraph('Perum Kutabumi 7 Astina Blok B'),
                $bodyXml,
                1
            );

            // Find the paragraph with dots for keperluan
            // Pattern: dots in paragraph after "keperluan"
            $bodyXml = preg_replace_callback(
                '/(<w:p[^>]*>.*?keperluan.*?)(<w:r>\\s*<w:rPr>.*?<w:sz w:val="23"\/><w:szCs w:val="23"\/><\/w:rPr>)(<w:t[^>]*>)\\.+(<\/w:t>)(.*?<\/w:p>)/s',
                function ($m) use ($fieldValues, $letter) {
                    $purpose = $this->getPurposeText($fieldValues, $letter);
                    $fontProps = $m[2];
                    $purposeXml = $m[3].htmlspecialchars($purpose, ENT_XML1, 'UTF-8').$m[4];

                    return $m[1].$fontProps.$purposeXml.$m[5];
                },
                $bodyXml,
                1
            );
        }

        // Remove all remaining dot sequences (…… or ...... or ....... etc)
        $bodyXml = preg_replace('/(<w:t[^>]*>)[……\.]+(<\/w:t>)/', '$1$2', $bodyXml ?? '');

        // Save modified XML
        $xmlContent = file_get_contents($docXmlPath);
        $xmlContent = preg_replace('/<w:body>.*<\/w:body>/s', $bodyXml ?? '', $xmlContent);
        file_put_contents($docXmlPath, $xmlContent);
    }

    private function buildReplacements($resident, $fieldValues, Letter $letter): array
    {
        $genderMap = ['l' => 'Laki-laki', 'p' => 'Perempuan', 'L' => 'Laki-laki', 'P' => 'Perempuan'];

        $replacements = [];

        // Nama -> full_name
        if ($resident) {
            $replacements['Nama'] = $resident->full_name ?? '';
            $replacements['Jenis Kelamin'] = $genderMap[$resident->gender] ?? ($resident->gender ?? '');
            $replacements['Tempat/Tgl. Lahir'] = trim(($resident->birth_place ?? '').', '.($resident->birth_date ? $resident->birth_date->translatedFormat('d F Y') : ''));
            $replacements['Agama'] = $resident->religion ?? '';
            $replacements['Pekerjaan'] = $resident->occupation ?? '';
            $replacements['Status Perkawinan'] = $resident->marital_status ?? '';

            // Alamat Sekarang - the value cell has static content + dots
            // We'll replace the dots part specifically
            $replacements['Alamat Sekarang'] = $resident->address ?? '';
        }

        return $replacements;
    }

    private function getPurposeText($fieldValues, Letter $letter): string
    {
        // Try letter_field_value with field_key 'keperluan' first
        if ($fieldValues->has('keperluan')) {
            return $fieldValues['keperluan']->value ?? '';
        }

        // Fall back to letter purpose field
        return $letter->purpose ?? '';
    }

    private function getCellText(\DOMXPath $xpath, \DOMNode $cell): string
    {
        $texts = $xpath->query('.//w:t', $cell);

        $result = '';
        foreach ($texts as $t) {
            $result .= $t->textContent;
        }

        return $result;
    }

    private function cellContainsDots(\DOMXPath $xpath, \DOMNode $cell): bool
    {
        $texts = $xpath->query('.//w:t', $cell);

        foreach ($texts as $t) {
            if (preg_match('/[……\.]{5,}/u', $t->textContent)) {
                return true;
            }
        }

        return false;
    }

    private function textContains(string $haystack, string $needle): bool
    {
        return mb_stripos($haystack, $needle) !== false;
    }

    private function replaceCellContent(\DOMXPath $xpath, \DOMNode $cell, string $value): void
    {
        // Find all <w:r> elements that contain dot text
        $runs = $xpath->query('.//w:r', $cell);

        $runsToRemove = [];
        $textNodesToUpdate = [];

        foreach ($runs as $run) {
            $texts = $xpath->query('.//w:t', $run);
            foreach ($texts as $t) {
                if (preg_match('/[……\.]{3,}/u', $t->textContent)) {
                    $textNodesToUpdate[] = $t;
                    $runsToRemove[] = $run;
                }
            }
        }

        // Replace text content of first dot run
        if (! empty($textNodesToUpdate)) {
            $firstText = $textNodesToUpdate[0];
            $escapedValue = htmlspecialchars($value, ENT_XML1, 'UTF-8');
            $firstText->nodeValue = $escapedValue;
        }

        // Remove remaining dot runs (skip if already removed)
        foreach ($runsToRemove as $run) {
            if ($run->parentNode !== null) {
                $run->parentNode->removeChild($run);
            }
        }
    }

    private function replaceDotsInCell(\DOMXPath $xpath, \DOMNode $cell, string $value): void
    {
        $runs = $xpath->query('.//w:r', $cell);
        $hasDots = false;

        foreach ($runs as $run) {
            $texts = $xpath->query('.//w:t', $run);
            foreach ($texts as $t) {
                if (preg_match('/[……\.]{3,}/u', $t->textContent)) {
                    $hasDots = true;
                    $t->nodeValue = htmlspecialchars($value, ENT_XML1, 'UTF-8');
                }
            }
        }
    }

    private function makeAddressParagraph(string $address): string
    {
        $escaped = htmlspecialchars($address, ENT_XML1, 'UTF-8');

        return <<<XML
<w:p w14:paraId="ADDRREPL" w14:textId="77777777" w:rsidR="00772F84" w:rsidRPr="001F655C" w:rsidRDefault="00433A28">
  <w:pPr><w:rPr><w:sz w:val="22"/><w:szCs w:val="22"/></w:rPr></w:pPr>
  <w:r w:rsidRPr="001F655C">
    <w:rPr><w:sz w:val="22"/><w:szCs w:val="22"/></w:rPr>
    <w:t xml:space="preserve">{$escaped}</w:t>
  </w:r>
</w:p>
XML;
    }

    private function addSignatureImage(string $tempDir, string $signaturePath, Letter $letter): void
    {
        $signatureContent = Storage::disk('private')->readStream($signaturePath);
        $pngData = stream_get_contents($signatureContent);
        fclose($signatureContent);

        // Get PNG dimensions
        $imgWidth = 0;
        $imgHeight = 0;
        $fpng = fopen('php://memory', 'r+b');
        fwrite($fpng, $pngData);
        rewind($fpng);

        $sigHeader = fread($fpng, 24);
        if (substr($sigHeader, 0, 8) === "\x89PNG\r\n\x08") {
            fseek($fpng, 16);
            $w = unpack('N', fread($fpng, 4));
            $h = unpack('N', fread($fpng, 4));
            $imgWidth = $w[1] ?? 300;
            $imgHeight = $h[1] ?? 100;
        }
        fclose($fpng);

        if ($imgWidth === 0) {
            $imgWidth = 300;
            $imgHeight = 100;
        }

        // EMU: 914400 EMU = 1 inch. At 96 DPI, 1px = 9525 EMU
        // Signature width: ~3.5cm = ~1243000 EMU
        $targetWidthEmu = 1243000;
        $targetHeightEmu = (int) round($targetWidthEmu * $imgHeight / $imgWidth);

        $mediaDir = $tempDir.'/word/media';
        if (! is_dir($mediaDir)) {
            mkdir($mediaDir, 0755);
        }

        // Find next available image number
        $existingImages = glob($mediaDir.'/image*.png') ?: [];
        $nextNum = count($existingImages) + 1;
        $imageFileName = "image{$nextNum}.png";
        $imagePathInZip = "word/media/{$imageFileName}";
        file_put_contents($mediaDir.'/'.$imageFileName, $pngData);

        // Update [Content_Types].xml to add PNG if not present
        $ctPath = $tempDir.'/[Content_Types].xml';
        $ctContent = file_get_contents($ctPath);
        if (strpos($ctContent, 'extension="png"') === false) {
            $ctContent = preg_replace(
                '/(<\/Types>)/',
                '<Default Extension="png" ContentType="image/png"/></Types>',
                $ctContent
            );
            file_put_contents($ctPath, $ctContent);
        }

        // Update document.xml.rels to add image relationship
        $relsPath = $tempDir.'/word/_rels/document.xml.rels';
        $relsContent = file_get_contents($relsPath);

        // Find next rId
        preg_match_all('/Id="rId(\d+)"/', $relsContent, $matches);
        $maxId = max(array_map('intval', $matches[1] ?? [0]));
        $newRId = 'rId'.($maxId + 1);

        $newRel = "<Relationship Id=\"{$newRId}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/image\" Target=\"media/{$imageFileName}\"/>";
        $relsContent = preg_replace('/(<\/Relationships>)/', $newRel.'$1', $relsContent);
        file_put_contents($relsPath, $relsContent);

        // Find the table with "RT. 005" (signature area) and insert signature image before it
        $docXmlPath = $tempDir.'/word/document.xml';
        $docContent = file_get_contents($docXmlPath);

        // Build signature image XML paragraph
        $signatureParagraph = $this->buildSignatureParagraphXml($newRId, $targetWidthEmu, $targetHeightEmu);

        // Find the table that contains "RT. 005" and insert before it
        if (preg_match('/(<w:tbl[^>]*>.*?<w:p[^>]*>.*?RT\.\\s*005.*?<\/w:tbl>)/s', $docContent, $match, PREG_OFFSET_CAPTURE)) {
            $tableStart = $match[0][1];
            $insertBefore = '<w:p';
            $insertPos = strrpos(substr($docContent, 0, $tableStart), $insertBefore);

            if ($insertPos !== false) {
                $docContent = substr($docContent, 0, $insertPos)
                    .$signatureParagraph
                    .substr($docContent, $insertPos);
            }
        } else {
            // Fallback: append before </w:body>
            $docContent = preg_replace('/(<\/w:body>)/', $signatureParagraph.'$1', $docContent);
        }

        file_put_contents($docXmlPath, $docContent);
    }

    private function buildSignatureParagraphXml(string $rId, int $widthEmu, int $heightEmu): string
    {
        return <<<XML
<w:p>
  <w:pPr>
    <w:jc w:val="right"/>
    <w:spacing w:after="0"/>
    <w:rPr>
      <w:sz w:val="22"/>
      <w:szCs w:val="22"/>
    </w:rPr>
  </w:pPr>
  <w:r>
    <w:rPr>
      <w:noProof/>
      <w:sz w:val="22"/>
      <w:szCs w:val="22"/>
    </w:rPr>
    <w:drawing>
      <wp:inline distT="0" distB="0" distL="0" distR="0" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing">
        <wp:extent cx="{$widthEmu}" cy="{$heightEmu}"/>
        <wp:effectExtent l="0" t="0" r="0" b="0"/>
        <wp:docPr id="99" name="Signature"/>
        <wp:cNvGraphicFramePr/>
        <a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
          <a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">
            <pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">
              <pic:nvPicPr>
                <pic:cNvPr id="99" name="signature.png"/>
                <pic:cNvPicPr/>
              </pic:nvPicPr>
              <pic:blipFill>
                <a:blip r:embed="{$rId}"/>
                <a:stretch>
                  <a:fillRect/>
                </a:stretch>
              </pic:blipFill>
              <pic:spPr>
                <a:xfrm>
                  <a:off x="0" y="0"/>
                  <a:ext cx="{$widthEmu}" cy="{$heightEmu}"/>
                </a:xfrm>
                <a:prstGeom prst="rect">
                  <a:avLst/>
                </a:prstGeom>
              </pic:spPr>
            </pic:pic>
          </a:graphicData>
        </a:graphic>
      </wp:inline>
    </w:drawing>
  </w:r>
</w:p>
XML;
    }

    private function packDocx(string $tempDir, Letter $letter): string
    {
        $tmpFile = sys_get_temp_dir().'/letter_gen_'.Str::uuid()->toString().'.docx';

        $zip = new \ZipArchive;
        $zip->open($tmpFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $this->addDirectoryToZip($zip, $tempDir, $tempDir);

        $zip->close();

        return $tmpFile;
    }

    private function addDirectoryToZip(\ZipArchive $zip, string $dir, string $baseDir): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $filePath = $file->getRealPath();
            $relativePath = ltrim(str_replace($baseDir, '', $filePath), '/\\');

            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($filePath, $relativePath);
            }
        }
    }

    private function storeDocument(string $docxPath, Letter $letter): string
    {
        $filename = $letter->reference_no.'.docx';
        $dir = config('letters.generated_path').'/'.$letter->id;

        Storage::disk('private')->putFileAs($dir, new File($docxPath), $filename);

        return $dir.'/'.$filename;
    }

    private function createLetterDocument(Letter $letter, string $storedPath): LetterDocument
    {
        $fileSize = Storage::disk('private')->size($storedPath);

        return LetterDocument::create([
            'letter_id' => $letter->id,
            'document_type' => 'final',
            'path' => $storedPath,
            'file_name' => $letter->reference_no.'.docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_size' => $fileSize,
        ]);
    }

    private function cleanup(string $tempDir): void
    {
        if (is_dir($tempDir)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($tempDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($files as $file) {
                if ($file->isDir()) {
                    rmdir($file->getRealPath());
                } else {
                    unlink($file->getRealPath());
                }
            }

            rmdir($tempDir);
        }
    }
}
