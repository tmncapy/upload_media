<?php
// upload.php - multi-file uploader with previews, per-file progress, and short base64 filenames
// Style: clean white/blue UI (sample 2)

// CONFIG
$uploadDir = __DIR__ . '/';
$maxFileSize = 100 * 1024 * 1024; // 100 MB default (change as needed)
$allowedMime = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'image/svg+xml',
    'video/mp4',
    'video/webm',
    'video/ogg',
    'audio/mpeg',
    'audio/ogg',
    'audio/wav',
    'audio/mp4'
];

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

function url_safe_base64($s)
{
    $b = base64_encode($s);
    return str_replace(['+', '/', '='], ['-', '_', ''], $b);
}

function short_encoded_name($origName)
{
    // timestamp + 3 random bytes, base64 url-safe, first 15 chars
    $seed = time() . '_' . bin2hex(random_bytes(3));
    return substr(url_safe_base64($seed), 0, 15);
}

// If AJAX upload (from JS), respond JSON per file
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES)) {
    $results = [];
    foreach ($_FILES as $field => $meta) {
        // PHP groups multiple files under arrays if name="files[]"
        if (is_array($meta['name'])) {
            $count = count($meta['name']);
            for ($i = 0; $i < $count; $i++) {
                $file = [
                    'name' => $meta['name'][$i],
                    'type' => $meta['type'][$i],
                    'tmp_name' => $meta['tmp_name'][$i],
                    'error' => $meta['error'][$i],
                    'size' => $meta['size'][$i]
                ];
                $results[] = handle_single_file($file, $uploadDir, $maxFileSize, $allowedMime);
            }
        } else {
            $results[] = handle_single_file($meta, $uploadDir, $maxFileSize, $allowedMime);
        }
    }

    header('Content-Type: application/json');
    echo json_encode($results);
    exit;
}

function handle_single_file($file, $uploadDir, $maxFileSize, $allowedMime)
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload error code: ' . $file['error']];
    }
    if ($file['size'] > $maxFileSize) {
        return ['success' => false, 'error' => 'File too large. Max ' . ($maxFileSize / 1024 / 1024) . ' MB'];
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowedMime)) {
        return ['success' => false, 'error' => 'Unsupported type: ' . $mime];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $mapExt = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/ogg' => 'ogv',
        'audio/mpeg' => 'mp3',
        'audio/ogg' => 'ogg',
        'audio/wav' => 'wav',
        'audio/mp4' => 'm4a'
    ];
    if (empty($ext) || strlen($ext) > 6) {
        $ext = isset($mapExt[$mime]) ? $mapExt[$mime] : 'bin';
    }

    $nameShort = short_encoded_name($file['name']);
    $newName = $nameShort . '.' . $ext;
    $dest = $uploadDir . '/' . $newName;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['success' => false, 'error' => 'Failed to move uploaded file. Check uploads/ permissions.'];
    }

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    $publicPath = $scriptDir . '/' . $newName;
    $url = $protocol . '://' . $host . $publicPath;

    return ['success' => true, 'url' => $url, 'filename' => $newName, 'mime' => $mime];
}
?>

<!doctype html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Upload Media</title>
    <style>
        :root {
            --bg: #f5f9ff;
            --card: #ffffff;
            --accent: #2563eb;
            --muted: #64748b
        }

        * {
            box-sizing: border-box;
            font-family: Inter, ui-sans-serif, system-ui, Segoe UI, Roboto, 'Helvetica Neue', Arial
        }

        body {
            margin: 0;
            background: var(--bg);
            color: #0f1724;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 24px
        }

        .wrap {
            width: 100%;
            max-width: 980px
        }

        .card {
            background: var(--card);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 6px 30px rgba(15, 23, 42, 0.08)
        }

        h1 {
            margin: 0;
            font-size: 20px
        }

        p.lead {
            color: var(--muted);
            margin: 6px 0 16px
        }

        .controls {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap
        }

        .fileinput {
            flex: 1
        }

        input[type=file] {
            display: block;
            width: 100%
        }

        button.primary {
            background: var(--accent);
            color: #fff;
            border: none;
            padding: 10px 14px;
            border-radius: 8px;
            cursor: pointer
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 16px;
            margin-top: 16px
        }

        .left {
            min-height: 200px
        }

        .preview-list {
            display: flex;
            flex-direction: column;
            gap: 12px
        }

        .preview {
            display: flex;
            gap: 12px;
            align-items: center;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid rgba(15, 23, 42, 0.05)
        }

        .thumb {
            width: 96px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f1f5f9;
            border-radius: 6px;
            overflow: hidden
        }

        .meta {
            flex: 1
        }

        .meta b {
            display: block
        }

        .progress {
            height: 8px;
            background: #eef2ff;
            border-radius: 6px;
            overflow: hidden;
            margin-top: 8px
        }

        .bar {
            height: 100%;
            width: 0%;
            background: var(--accent)
        }

        .actions {
            display: flex;
            gap: 8px
        }

        .link {
            color: var(--accent);
            font-weight: 600;
            text-decoration: none
        }

        .small {
            font-size: 13px;
            color: var(--muted)
        }

        .right {
            background: #fbfdff;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid rgba(15, 23, 42, 0.03)
        }

        .btn-ghost {
            background: transparent;
            border: 1px solid rgba(15, 23, 42, 0.06);
            padding: 8px;
            border-radius: 8px;
            cursor: pointer
        }
    </style>
</head>

<body>
    <div class="wrap">
        <div class="card">
            <h1>Upload Media</h1>
            <p class="lead">Chọn 1 hoặc nhiều file media (ảnh/video/âm thanh). Giới hạn kích thước mỗi file là
                <?php echo ($maxFileSize / 1024 / 1024); ?> MB.
            </p>

            <div class="controls">
                <div class="fileinput">
                    <input id="fileInput" type="file" multiple accept="image/*,video/*,audio/*">
                </div>
                <button id="startUpload" class="primary">Upload</button>
                <div class="small">Đã chọn: <span id="count">0</span></div>
            </div>

            <div class="grid">
                <div class="left">
                    <div id="previewList" class="preview-list"></div>
                </div>
            </div>

        </div>
    </div>

    <script>
        const fileInput = document.getElementById('fileInput');
        const previewList = document.getElementById('previewList');
        const startBtn = document.getElementById('startUpload');
        const countEl = document.getElementById('count');
        let files = []; // {file, id, el, progressEl, statusEl}

        function humanSize(n) {
            if (n < 1024) return n + ' B';
            let u = ['KB', 'MB', 'GB']; let i = -1; do { n /= 1024; i++; } while (n >= 1024 && i < u.length - 1);
            return n.toFixed(2) + ' ' + u[i];
        }

        fileInput.addEventListener('change', (e) => {
            addFiles(Array.from(e.target.files));
        });

        function addFiles(list) {
            for (const f of list) {
                const id = Math.random().toString(36).slice(2, 9);
                const el = document.createElement('div'); el.className = 'preview';
                const thumb = document.createElement('div'); thumb.className = 'thumb';
                const meta = document.createElement('div'); meta.className = 'meta';
                const actions = document.createElement('div'); actions.className = 'actions';

                const nameB = document.createElement('b'); nameB.textContent = f.name;
                const info = document.createElement('div'); info.className = 'small'; info.textContent = f.type + ' • ' + humanSize(f.size);
                const progress = document.createElement('div'); progress.className = 'progress';
                const bar = document.createElement('div'); bar.className = 'bar'; progress.appendChild(bar);
                const status = document.createElement('div'); status.className = 'small'; status.textContent = 'Ready';

                const removeBtn = document.createElement('button'); removeBtn.className = 'btn-ghost'; removeBtn.textContent = 'Remove';
                removeBtn.onclick = () => { previewList.removeChild(el); files = files.filter(x => x.id !== id); countEl.textContent = files.length; };

                actions.appendChild(removeBtn);

                // preview content
                const reader = new FileReader();
                reader.onload = (ev) => {
                    if (f.type.startsWith('image/')) {
                        const img = document.createElement('img'); img.src = ev.target.result; img.style.maxWidth = '100%'; img.style.maxHeight = '100%';
                        thumb.appendChild(img);
                    } else if (f.type.startsWith('video/')) {
                        const v = document.createElement('video'); v.src = ev.target.result; v.controls = true; v.style.maxWidth = '100%'; v.style.maxHeight = '100%';
                        thumb.appendChild(v);
                    } else if (f.type.startsWith('audio/')) {
                        const a = document.createElement('audio'); a.src = ev.target.result; a.controls = true; a.style.width = '100%';
                        thumb.appendChild(a);
                    } else {
                        thumb.textContent = f.name.split('.').pop().toUpperCase();
                    }
                };
                reader.readAsDataURL(f);

                meta.appendChild(nameB); meta.appendChild(info); meta.appendChild(progress); meta.appendChild(status);
                el.appendChild(thumb); el.appendChild(meta); el.appendChild(actions);
                previewList.appendChild(el);

                files.push({ file: f, id, el, bar, status });
            }
            countEl.textContent = files.length;
        }

        startBtn.addEventListener('click', () => {
            if (files.length === 0) return alert('Chưa chọn file nào');
            // upload each file sequentially or parallel; we'll do parallel but limit concurrency
            const concurrency = 3;
            let idx = 0;
            let running = 0;
            function next() {
                while (running < concurrency && idx < files.length) {
                    uploadFile(files[idx]); idx++; running++;
                }
                if (running === 0 && idx >= files.length) { console.log('All done'); }
            }
            next();

            function uploadFile(item) {
                const xhr = new XMLHttpRequest();
                const fd = new FormData();
                fd.append('file', item.file);

                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) {
                        const pct = Math.round(e.loaded / e.total * 100);
                        item.bar.style.width = pct + '%';
                    }
                });

                xhr.onreadystatechange = () => {
                    if (xhr.readyState === 4) {
                        running--;
                        if (xhr.status === 200) {
                            try {
                                const res = JSON.parse(xhr.responseText);
                                // when multiple files uploaded, server returns array; if single returns array too
                                const first = (Array.isArray(res) ? res[0] : res);
                                if (first.success) {
                                    item.status.textContent = 'Uploaded';
                                    const a = document.createElement('a'); a.href = first.url; a.textContent = first.url; a.className = 'link'; a.target = '_blank';
                                    item.el.querySelector('.meta').appendChild(a);
                                } else {
                                    item.status.textContent = 'Error: ' + (first.error || 'Unknown');
                                }
                            } catch (err) {
                                item.status.textContent = 'Server error';
                            }
                        } else {
                            item.status.textContent = 'HTTP ' + xhr.status;
                        }
                        next();
                    }
                };

                xhr.open('POST', location.href);
                xhr.send(fd);
                item.status.textContent = 'Uploading...';
            }
        });
    </script>
</body>

</html>