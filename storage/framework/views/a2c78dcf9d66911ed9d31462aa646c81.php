
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
            /* background: #fff; */
        }

        body {
            font-family: "Times New Roman", Times, serif;
            color: #000;
            padding: 2mm 5mm 12.7mm 5mm;
        }

        /* ── LETTERHEAD ── */
        .letterhead {
            position: relative;
            border-bottom: 2px solid #000;
            padding-bottom: 2mm;
            margin-bottom: 2mm;
        }

        .letterhead__logo {
            position: absolute;
            left: 12mm;
            top: 0;
            width: 14mm;
        }

        .letterhead__titles {
            padding-left: 18mm;
            text-align: center;
        }

        .letterhead__titles div:nth-child(1),
        .letterhead__titles div:nth-child(2),
        .letterhead__titles div:nth-child(3) {
            font-size: 10.5pt;
            font-weight: bold;
            white-space: nowrap;
            line-height: 1.15;
        }

        /* ── TITLE ── */
        .letter-title {
            text-align: center;
            font-size: 12pt;
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 2mm;
            letter-spacing: 0.3pt;
        }

        /* ── CONTENT ── */
        .content {
            font-size: 11pt;
            line-height: 1.4;
        }

        .content p {
            margin: 0;
        }

        .opening {
            text-align: justify;
            text-indent: 6mm;
            line-height: 1.4;
        }

        /* ── IDENTITY TABLE ── */
        .identity {
            width: 100%;
            border-collapse: collapse;
            font-size: 11pt;
            line-height: 1.3;
            margin-top: 1mm;
        }

        .identity td {
            padding: 0.3mm 0 0.2mm;
            vertical-align: top;
        }

        .identity td:nth-child(1) {
            width: 36mm;
            white-space: nowrap;
        }

        .identity td:nth-child(2) {
            width: 4mm;
            text-align: center;
        }

        .identity td:nth-child(3) {
            width: auto;
        }

        /* ── ADDRESS ROW ── */

        /* ── PURPOSE ── */
        .purpose {
            text-align: justify;
            line-height: 1.4;
            margin-top: 1mm;
        }

        .inline-dots {
            display: inline-block;
            width: 42mm;
            height: 1.8mm;
            vertical-align: baseline;
            border-bottom: 0.6mm dotted #000;
        }

        /* ── CLOSING ── */
        .closing {
            text-align: justify;
            text-indent: 6mm;
            line-height: 1.4;
            margin-top: 0.5mm;
        }

        /* ── DATE ── */
        .date-row {
            width: 100%;
            font-size: 11pt;
            line-height: 1.3;
            margin-top: 2mm;
        }

        .date-row-right {
            text-align: right;
        }

        /* ── SIGNATURES ── */
        /* ── SIGNATURES ── */
.signatures {
    display: table;
    width: 100%;
    table-layout: fixed;
    margin-top: 4mm;
    font-size: 11pt;
    line-height: 1.3;
    text-align: center;
    page-break-inside: avoid;
}

.signature {
    display: table-cell;
    position: relative;
    width: 50%;
    height: 32mm;
    vertical-align: top;
}

.signature--rw {
    padding-right: 6mm;
}

.signature--rt {
    padding-left: 6mm;
}

.signature__title,
.signature__official {
    margin: 0;
}

/*
 * Nama diletakkan di bagian bawah area tanda tangan.
 * Height pada .signature menghasilkan ruang kosong
 * antara jabatan dan nama.
 */
.signature__name {
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    margin: 0;
    font-size: 11pt;
    font-weight: 700;
    text-decoration: underline;
    white-space: nowrap;
}

/* Area tanda tangan dan stempel Ketua RT */
.rt-slot {
    position: absolute;
    top: 7mm;
    left: 50%;
    transform: translateX(-50%);
    width: 30mm;
    height: 20mm;
}

.rt-slot__sign {
    position: absolute;
    top: 1mm;
    left: 50%;
    transform: translateX(-50%);
    width: 40mm;
    height: auto;
    z-index: 1;
}

.rt-slot__stamp {
    position: absolute;
    top: 2mm;
    left: 50%;
    transform: translateX(-50%) rotate(-5deg);
    width: 52mm;
    height: auto;
    opacity: 0.88;
    z-index: 2;
}

        .signature__name {
            font-size: 11pt;
            font-weight: 700;
            text-decoration: underline;
            white-space: nowrap;
        }

        .signature--rw .signature__name {
            margin-top: 1mm;
        }

        .signature--rt {
            position: relative;
        }

        .signature--rt .signature__name {
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
        }

        @media screen {
            body {
                background: #ddd;
                padding: 5mm;
            }

            .letterhead__logo {
                width: 80px;
            }
        }
    </style>
</head>

<body>
    <?php if($logo): ?>
        <img class="letterhead__logo" src="<?php echo e($logo); ?>" alt="Logo RT/RW">
    <?php endif; ?>
    <header class="letterhead">
        <div class="letterhead__titles">
            <div>RUKUN WARGA (RW) 016</div>
            <div>RUKUN TETANGGA (RT) 005</div>
            <div>PERUM KUTABUMI 7 ASTINA KELURAHAN SUKATANI</div>
        </div>
    </header>
    <section class="content">
        <p class="opening">
            Yang bertanda tangan di bawah ini Ketua RT.005 RW.016 Perum Kutabumi 7
            Astina Kelurahan Sukatani Kecamatan Rajeg Kabupaten Tangerang,
            menerangkan bahwa :
        </p>
        <table class="identity" aria-label="Data warga">
            <tbody>
                <?php $__currentLoopData = $fields; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr>
                        <td><?php echo e($field['label']); ?></td>
                        <td>:</td>
                        <td><?php echo e($field['value']); ?></td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <tr class="identity-address-row">
                    <td>Alamat Sekarang</td>
                    <td>:</td>
                    <td>
                        Perum Kutabumi 7 Astina Blok <?php echo e($block); ?><br>
                        Kel. Sukatani Kec. Rajeg Kab. Tangerang.
                    </td>
                </tr>
            </tbody>
        </table>
        <p class="purpose">
            Adalah benar penduduk/warga kami yang bertempat tinggal pada alamat tersebut di atas.
            Surat pengantar ini diberikan untuk keperluan : <?php echo e($purpose); ?>

        </p>
        <p class="closing">
            Demikian surat pengantar ini dibuat untuk diketahui dan dipergunakan sebagaimana mestinya.
        </p>
    </section>
    <div class="date-row">
        <div class="date-row-right">
            <span>Sukatani,</span> <span><?php echo e($date); ?></span>
        </div>
    </div>
    <div class="signatures">
        <div class="signature signature--rw">
            <div class="signature__title">Mengetahui,</div>
            <div class="signature__official">Ketua RW. 016</div>
            <div class="signature__name">KARMAN SUHENDRA</div>
        </div>
        <div class="signature signature--rt">
            <div class="signature__official">Ketua RT. 005</div>
            <div class="rt-slot">
                <?php if($signature): ?>
                    <img class="rt-slot__sign" src="<?php echo e($signature); ?>" alt="">
                <?php endif; ?>
                <?php if($stamp): ?>
                    <img class="rt-slot__stamp" src="<?php echo e($stamp); ?>" alt="">
                <?php endif; ?>
            </div>
            <div class="signature__name"><?php echo e($rt_signature_name ?: 'GILANG CHOIRUR R.'); ?></div>
        </div>
    </div>
</body>

</html>
<?php /**PATH C:\Users\owi\astina-smart-mobile\astina-be\resources\views/letters/surat-pengantar.blade.php ENDPATH**/ ?>