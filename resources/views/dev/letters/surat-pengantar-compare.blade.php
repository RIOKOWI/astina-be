<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Compare: Surat Pengantar</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: system-ui, -apple-system, sans-serif;
    background: #1a1a1a;
    color: #e0e0e0;
    min-height: 100vh;
}

.toolbar {
    position: sticky;
    top: 0;
    z-index: 100;
    background: #252525;
    border-bottom: 1px solid #444;
    padding: 12px 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}

.toolbar h1 {
    font-size: 16px;
    font-weight: 600;
    color: #fff;
    margin-right: auto;
}

.toolbar .mode-group {
    display: flex;
    gap: 4px;
    background: #333;
    border-radius: 6px;
    padding: 3px;
}

.mode-btn {
    padding: 6px 14px;
    border: none;
    background: transparent;
    color: #aaa;
    cursor: pointer;
    border-radius: 4px;
    font-size: 13px;
    font-family: inherit;
    transition: background 0.15s, color 0.15s;
}
.mode-btn.active {
    background: #4a9eff;
    color: #fff;
}
.mode-btn:hover:not(.active) {
    background: #3a3a3a;
    color: #fff;
}

.opacity-control {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #aaa;
}
.opacity-control input[type="range"] {
    width: 120px;
    accent-color: #4a9eff;
}
.opacity-value { min-width: 36px; }

.overlay-container {
    display: none;
    position: relative;
    margin: 20px auto;
    max-width: 900px;
}
.overlay-container.active { display: block; }

.overlay-img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: auto;
    pointer-events: none;
}

.comparison {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0;
    padding: 20px;
    max-width: 1800px;
    margin: 0 auto;
}
.comparison.hidden { display: none; }

.col {
    background: #2a2a2a;
    border-radius: 8px;
    overflow: hidden;
}
.col + .col { margin-left: 16px; }

.col-header {
    padding: 10px 14px;
    background: #333;
    font-size: 12px;
    font-weight: 600;
    color: #bbb;
    border-bottom: 1px solid #444;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.col-header a {
    color: #4a9eff;
    text-decoration: none;
    font-size: 12px;
}
.col-header a:hover { text-decoration: underline; }

.col iframe {
    width: 100%;
    height: calc(100vh - 160px);
    border: none;
    display: block;
}
</style>
</head>
<body>

<div class="toolbar">
    <h1>Surat Pengantar — Visual Comparison</h1>

    <div class="mode-group">
        <button class="mode-btn active" id="btn-side">Side-by-side</button>
        <button class="mode-btn" id="btn-overlay">Overlay</button>
    </div>

    <div class="opacity-control" id="opacity-control" style="display:none">
        <span>Opacity:</span>
        <input type="range" id="opacity-range" min="0" max="100" value="50">
        <span class="opacity-value" id="opacity-value">50%</span>
    </div>
</div>

<div class="comparison" id="comparison">
    <div class="col">
        <div class="col-header">
            Reference (docs/Letter/surat-pengantar.png)
        </div>
        <iframe id="ref-frame" src="/dev/letters/surat-pengantar/reference"></iframe>
    </div>
    <div class="col">
        <div class="col-header">
            Rendered Preview
            <a href="/dev/letters/surat-pengantar" target="_blank">Open</a>
        </div>
        <iframe id="preview-frame" src="/dev/letters/surat-pengantar"></iframe>
    </div>
</div>

<div class="overlay-container" id="overlay-container">
    <img id="overlay-ref" src="/dev/letters/surat-pengantar/reference" alt="Reference">
    <img id="overlay-preview" class="overlay-img" src="/dev/letters/surat-pengantar" alt="Preview" style="opacity:0.5">
</div>

<script>
const btnSide = document.getElementById('btn-side');
const btnOverlay = document.getElementById('btn-overlay');
const comparison = document.getElementById('comparison');
const overlayContainer = document.getElementById('overlay-container');
const opacityControl = document.getElementById('opacity-control');
const opacityRange = document.getElementById('opacity-range');
const opacityValue = document.getElementById('opacity-value');
const overlayPreview = document.getElementById('overlay-preview');

btnSide.addEventListener('click', () => {
    btnSide.classList.add('active');
    btnOverlay.classList.remove('active');
    comparison.classList.remove('hidden');
    overlayContainer.classList.remove('active');
    opacityControl.style.display = 'none';
});

btnOverlay.addEventListener('click', () => {
    btnOverlay.classList.add('active');
    btnSide.classList.remove('active');
    comparison.classList.add('hidden');
    overlayContainer.classList.add('active');
    opacityControl.style.display = 'flex';
});

opacityRange.addEventListener('input', () => {
    const v = opacityRange.value;
    opacityValue.textContent = v + '%';
    overlayPreview.style.opacity = v / 100;
});
</script>
</body>
</html>
