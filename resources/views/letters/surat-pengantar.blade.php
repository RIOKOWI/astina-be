{{--
    Surat Pengantar RT 005 RW 016 — A5 portrait
    Visual layout ported from: docs/Letter/index.html + docs/Letter/style.css
    Canonical reference: docs/Letter/convert3.md

    A5: 148mm x 210mm portrait
    Page padding: top 2mm (logo float zone), bottom 12.7mm, left/right 5mm
    Logo: public/img/logo-rt.webp (positioned absolutely above header)
    Header: uses reference's symmetric-padding centered titles layout
--}}
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Surat Pengantar RT 005 RW 016</title>
<style>
@page {
    size: A5 portrait;
    margin: 0;
}
* {
    box-sizing: border-box;
}
html,
body {
    margin: 0;
    padding: 0;
    background: #fff;
}
body {
    font-family: "Times New Roman", Times, serif;
    color: #000;
    /* Top 2mm = logo float zone; bottom 12.7mm = page margin */
    padding: 2mm 5mm 12.7mm 5mm;
}
/* ── LETTERHEAD ── */
.letterhead {
    position: relative;
    border-bottom: 1px solid #000;
}
/* Logo: width only — no height so DOMPDF auto-calculates proportional height
   without allocating fixed vertical space. */
.letterhead__logo {
    position: absolute;
    left: 3mm;
    top: 1.9mm;
    width: 18mm;
}
/* Symmetric padding → titles stay geometrically centered */
.letterhead__titles {
    width: 100%;
    padding-left: 24mm;
    padding-right: 24mm;
    text-align: center;
    font-weight: bold;
    line-height: 1.1;
}
.letterhead__titles div:nth-child(1),
.letterhead__titles div:nth-child(2) {
    font-size: 10.5pt;
    white-space: nowrap;
}
.letterhead__titles div:nth-child(3) {
    font-size: 8.5pt;
    white-space: nowrap;
}
/* ── CONTENT ── */
.content {
    font-size: 11.5pt;
    line-height: 1.5;
}
.content p {
    margin: 0;
}
.opening {
    margin-top: 0mm !important;
    text-align: justify;
    text-indent: 5mm;
}
.identity {
    width: 86%;
    margin: 1mm auto 0;
    border-collapse: collapse;
    table-layout: fixed;
    font-size: 11pt;
    line-height: 1.0;
}
.identity td {
    padding: 0.3mm 0 0.2mm;
    vertical-align: top;
}
.identity td:nth-child(1) {
    width: 34mm;
    white-space: nowrap;
}
.identity td:nth-child(2) {
    width: 4.2mm;
    text-align: center;
}
.identity td:nth-child(3) {
    width: auto;
}
.dotline {
    display: block;
    height: 1.8mm;
    border-bottom: 0.55mm dotted #000;
}
.identity-address-row td {
    padding-top: 0.5mm;
    line-height: 1.1;
}
.purpose {
    margin-top: 1.5mm !important;
    text-align: justify;
    line-height: 1.5;
}
.inline-dots {
    display: inline-block;
    width: 58mm;
    height: 2mm;
    vertical-align: baseline;
    border-bottom: 0.55mm dotted #000;
}
.closing {
    margin-top: 0.5mm !important;
    text-align: justify;
    text-indent: 5mm;
    line-height: 1.5;
}
.date-row {
    width: 50.5mm;
    margin: 1mm 1.7mm 0 auto;
    display: flex;
    justify-content: space-between;
    font-size: 11.5pt;
    line-height: 1;
}
.date-row .date-loc {
    padding-left: 0;
}
.date-row .date-val {
    padding-left: 12mm;
}
.signatures {
    display: table;
    width: 100%;
    table-layout: fixed;
    margin-top: 3mm;
    font-size: 11.5pt;
    line-height: 1.3;
    text-align: center;
    page-break-inside: avoid;
}
.signature {
    display: table-cell;
    width: 50%;
    vertical-align: top;
}
.signature--rw {
    padding-left: 5mm;
    padding-right: 5mm;
}
.signature--rt {
    padding-left: 7mm;
}
.signature__name {
    margin-top: 14mm;
    font-size: 11.5pt;
    font-weight: 700;
    text-decoration: underline;
    white-space: nowrap;
}
.slot {
    /* stamp + sign stack vertically, no extra space */
}
.stamp {
    display: block;
    max-width: 22mm;
    max-height: 22mm;
}
.sign {
    display: block;
    max-width: 22mm;
    max-height: 12mm;
}
@media screen {
    body {
        background: #ddd;
        padding: 10mm 0;
    }
}
</style>
</head>
<body>
@if ($logo)<img class="letterhead__logo" src="{{ $logo }}" alt="Logo RT/RW">@endif
<header class="letterhead">
<div class="letterhead__titles">
<div>RUKUN WARGA (RW) 016</div>
<div>RUKUN TETANGGA (RT) 005</div>
<div>PERUM KUTABUMI 7 ASTINA KELURAHAN SUKATANI</div>
</div>
</header>
<section class="content">
<p class="opening">
Yang bertanda tangan di bawah ini Ketua RT.005 RW.016 Perum Kuttobufumi 7
Astina Kelurahan Sukatani Kecamatan Rajeg Kabupaten Tangerang,menerangkan
bahwa&nbsp;:
</p>
<table class="identity" aria-label="Data warga">
<tbody>
@foreach ($fields as $field)
<tr>
<td>{{ $field['label'] }}</td>
<td>:</td>
<td>
{{ $field['value'] }}
<span class="dotline"></span>
</td>
</tr>
@endforeach
<tr class="identity-address-row">
<td>Alamat Sekarang</td>
<td>:</td>
<td>
Perum Kuttobufumi 7 Astina Blok {{ $block }}<br>
Kelurahan Sukatani Kec. Rajeg Kab. Tangerang.
<span class="dotline"></span>
</td>
</tr>
</tbody>
</table>
<p class="purpose">
Adalah benar penduduk/warga kami yang berturut tinggal pada alamat tersebut di
atas. Surat pengantar ini diberikan untuk keperluan&nbsp;{{ $purpose }}<span class="inline-dots"></span>
</p>
<p class="closing">
Demikian surat pengantar ini dibuat untuk diketahui dan dipergunakan
sebagaimana mestinya.
</p>
<div class="date-row">
<span class="date-loc">Sukatani,</span>
<span class="date-val">{{ $date }}</span>
</div>
<div class="signatures">
<div class="signature signature--rw">
<div>Mengetahui,</div>
<div>Ketua RW. 016</div>
<div class="signature__name">KARMAN SUHENDRA</div>
</div>
<div class="signature signature--rt">
<div>&nbsp;</div>
<div>Ketua RT. 005</div>
<div class="slot">
@if ($stamp)<img class="stamp" src="{{ $stamp }}" alt="">@endif
@if ($signature)<img class="sign" src="{{ $signature }}" alt="">@endif
</div>
<div class="signature__name">GILANG CHOIRUR R.</div>
</div>
</div>
</section>
</body>
</html>
