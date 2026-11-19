<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Document - {{ $documentName }}</title>
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8fafc; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; }
        .card { background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); width: 100%; max-width: 600px; }
        h1 { margin-top: 0; font-size: 1.5rem; color: #1e293b; text-align: center; }
        p { color: #64748b; text-align: center; margin-bottom: 2rem; }
        canvas { border: 2px dashed #cbd5e1; border-radius: 8px; width: 100%; height: 200px; cursor: crosshair; background: #f1f5f9; touch-action: none; }
        .form-group { margin-bottom: 1.5rem; }
        label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: #334155; }
        input[type="text"] { width: 100%; padding: 0.8rem; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; font-size: 1rem; }
        .actions { display: flex; gap: 10px; margin-top: 1rem; }
        button { flex: 1; padding: 1rem; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-primary:hover { background: #2563eb; }
        .btn-secondary { background: #e2e8f0; color: #475569; }
        .btn-secondary:hover { background: #cbd5e1; }
        #success-message { display: none; text-align: center; color: #10b981; font-weight: 600; margin-top: 1rem; }
    </style>
</head>
<body>
    <div class="card" id="signContainer">
        <h1>Review & Sign</h1>
        <p>You are signing: <strong>{{ $documentName }}</strong></p>
        
        <div class="form-group">
            <label>Full Name</label>
            <input type="text" id="signerName" placeholder="Print your name..." required>
        </div>

        <div class="form-group">
            <label>Draw Signature</label>
            <canvas id="signatureCanvas"></canvas>
            <div style="text-align: right; margin-top: 5px;">
                <button type="button" class="btn-secondary" style="padding: 0.5rem; font-size: 0.8rem; flex: none;" onclick="clearCanvas()">Clear Signature</button>
            </div>
        </div>

        <div class="actions">
            <button class="btn-primary" onclick="submitSignature()">I Agree & Sign Document</button>
        </div>
        
        <div id="success-message">
            ✅ Document signed successfully! You may close this window.
        </div>
    </div>

    <script>
        const canvas = document.getElementById('signatureCanvas');
        const ctx = canvas.getContext('2d');
        let isDrawing = false;

        // Resize canvas correctly
        function resizeCanvas() {
            const rect = canvas.parentElement.getBoundingClientRect();
            canvas.width = rect.width;
            canvas.height = 200;
        }
        window.addEventListener('resize', resizeCanvas);
        resizeCanvas();

        // Drawing events
        canvas.addEventListener('mousedown', startPos);
        canvas.addEventListener('mouseup', endPos);
        canvas.addEventListener('mousemove', draw);
        
        // Touch support
        canvas.addEventListener('touchstart', (e) => { e.preventDefault(); startPos(e.touches[0]); });
        canvas.addEventListener('touchend', (e) => { e.preventDefault(); endPos(); });
        canvas.addEventListener('touchmove', (e) => { e.preventDefault(); draw(e.touches[0]); });

        function startPos(e) {
            isDrawing = true;
            draw(e);
        }

        function endPos() {
            isDrawing = false;
            ctx.beginPath();
        }

        function draw(e) {
            if (!isDrawing) return;
            const rect = canvas.getBoundingClientRect();
            const x = (e.clientX || e.pageX) - rect.left;
            const y = (e.clientY || e.pageY) - rect.top;
            
            ctx.lineWidth = 3;
            ctx.lineCap = 'round';
            ctx.strokeStyle = '#0f172a';
            
            ctx.lineTo(x, y);
            ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(x, y);
        }

        function clearCanvas() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        }

        function isCanvasBlank(canvas) {
            const blank = document.createElement('canvas');
            blank.width = canvas.width;
            blank.height = canvas.height;
            return canvas.toDataURL() === blank.toDataURL();
        }

        function submitSignature() {
            const name = document.getElementById('signerName').value;
            if(!name) { alert('Please enter your full name.'); return; }
            if(isCanvasBlank(canvas)) { alert('Please draw your signature before submitting.'); return; }

            const dataURL = canvas.toDataURL('image/png');
            
            fetch("{{ route('signature.save', ['type' => $type, 'id' => $id]) }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    signer_name: name,
                    signature_data: dataURL
                })
            }).then(res => res.json())
            .then(data => {
                if(data.success) {
                    document.getElementById('success-message').style.display = 'block';
                    setTimeout(() => {
                        window.close();
                        document.body.innerHTML = "<h2 style='text-align:center; padding: 50px;'>Document Signed Successfully. You may close this page.</h2>";
                    }, 2000);
                } else {
                    alert(data.message || 'An error occurred.');
                }
            }).catch(err => {
                alert('Network error. Please try again.');
            });
        }
    </script>
</body>
</html>
