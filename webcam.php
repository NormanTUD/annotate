<?php
	include("header.php");
	include_once("functions.php");

	$available_models = get_list_of_models();
?>

<style>
	#webcam_container {
		position: relative;
		display: inline-block;
		margin: 10px 0;
	}

	#webcam_video {
		max-width: 100%;
		border: 2px solid #45475a;
		border-radius: 8px;
	}

	#webcam_canvas {
		position: absolute;
		top: 0;
		left: 0;
		pointer-events: none;
		border-radius: 8px;
	}

	#capture_canvas {
		display: none;
	}

	.webcam-controls {
		display: flex;
		align-items: center;
		gap: 12px;
		flex-wrap: wrap;
		margin: 10px 0;
		padding: 12px 16px;
		background: #1e1e2e;
		border: 1px solid #333;
		border-radius: 8px;
	}

	.webcam-controls label {
		color: #cdd6f4;
		font-size: 13px;
	}

	.webcam-controls select,
	.webcam-controls input {
		background: #313244;
		color: #cdd6f4;
		border: 1px solid #45475a;
		border-radius: 4px;
		padding: 4px 8px;
		font-size: 13px;
	}

	.webcam-controls button {
		padding: 8px 16px;
		border: none;
		border-radius: 6px;
		cursor: pointer;
		font-weight: 600;
		font-size: 13px;
		transition: background 0.2s;
	}

	#btn_start_webcam {
		background: #89b4fa;
		color: #1e1e2e;
	}

	#btn_stop_webcam {
		background: #f38ba8;
		color: #1e1e2e;
	}

	#btn_capture {
		background: #a6e3a1;
		color: #1e1e2e;
	}

	#btn_toggle_predict {
		background: #cba6f7;
		color: #1e1e2e;
	}

	#btn_toggle_predict.active {
		background: #f9e2af;
		color: #1e1e2e;
	}

	#prediction_info {
		margin-top: 10px;
		padding: 8px 12px;
		background: #181825;
		border: 1px solid #45475a;
		border-radius: 6px;
		color: #cdd6f4;
		font-size: 12px;
		font-family: monospace;
		min-height: 40px;
	}

	#captured_images {
		margin-top: 20px;
		display: flex;
		flex-wrap: wrap;
		gap: 10px;
	}

	#captured_images .captured-thumb {
		position: relative;
		border: 1px solid #45475a;
		border-radius: 6px;
		overflow: hidden;
	}

	#captured_images .captured-thumb img {
		max-height: 150px;
		display: block;
	}

	#captured_images .captured-thumb .thumb-actions {
		position: absolute;
		bottom: 0;
		left: 0;
		right: 0;
		background: rgba(0,0,0,0.7);
		padding: 4px;
		display: flex;
		gap: 4px;
		justify-content: center;
	}

	#captured_images .captured-thumb .thumb-actions button {
		font-size: 11px;
		padding: 3px 8px;
		border: none;
		border-radius: 3px;
		cursor: pointer;
	}

	.upload-status {
		position: absolute;
		top: 4px;
		right: 4px;
		background: rgba(0,0,0,0.7);
		color: #a6e3a1;
		font-size: 11px;
		padding: 2px 6px;
		border-radius: 3px;
		font-weight: bold;
	}

	.upload-status.error {
		color: #f38ba8;
	}

	.upload-status.uploading {
		color: #f9e2af;
	}
</style>

<h1>&#128247; Live Webcam & Predictions</h1>

<div class="webcam-controls">
	<label>Camera:
		<select id="camera_select">
			<option value="">Loading cameras...</option>
		</select>
	</label>

	<label>Model:
		<select id="webcam_model_select">
			<option value="none">None (no predictions)</option>
			<?php foreach ($available_models as $_model): ?>
				<option value="<?php echo htmlspecialchars($_model[1]); ?>">
					<?php echo htmlspecialchars($_model[0]); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</label>

	<label>Confidence:
		<input type="range" id="webcam_conf_slider" min="0" max="1" step="0.01" value="0.3">
		<span id="webcam_conf_value">0.30</span>
	</label>

	<label>FPS:
		<input type="number" id="webcam_fps" min="1" max="30" value="5" style="width: 50px;">
	</label>

	<button id="btn_start_webcam" onclick="startWebcam()">&#9654; Start Webcam</button>
	<button id="btn_stop_webcam" onclick="stopWebcam()" style="display:none;">&#9209; Stop</button>
	<button id="btn_toggle_predict" onclick="togglePrediction()">&#129302; Start Predictions</button>
	<button id="btn_capture" onclick="captureFrame()">&#128248; Capture & Upload</button>
</div>

<div id="webcam_container">
	<video id="webcam_video" autoplay playsinline muted width="800"></video>
	<canvas id="webcam_canvas" width="800" height="600"></canvas>
</div>
<canvas id="capture_canvas"></canvas>

<div id="prediction_info">Predictions will appear here...</div>

<h3 style="margin-top: 20px;">Captured Frames</h3>
<p style="color: #a6adc8; font-size: 12px;">Frames are automatically uploaded for annotation when captured.</p>
<div id="captured_images"></div>

<script>
(function() {
	"use strict";

	let webcamStream = null;
	let predictionLoop = null;
	let isPredicting = false;
	let webcamModel = null;
	let webcamLabels = [];
	let capturedCount = 0;

	const video = document.getElementById('webcam_video');
	const overlayCanvas = document.getElementById('webcam_canvas');
	const overlayCtx = overlayCanvas.getContext('2d');
	const captureCanvas = document.getElementById('capture_canvas');
	const captureCtx = captureCanvas.getContext('2d');

	// Enumerate cameras
	async function enumerateCameras() {
		try {
			const tempStream = await navigator.mediaDevices.getUserMedia({ video: true });
			tempStream.getTracks().forEach(t => t.stop());

			const devices = await navigator.mediaDevices.enumerateDevices();
			const videoDevices = devices.filter(d => d.kind === 'videoinput');
			const select = document.getElementById('camera_select');
			select.innerHTML = '';

			videoDevices.forEach((device, idx) => {
				const option = document.createElement('option');
				option.value = device.deviceId;
				option.textContent = device.label || `Camera ${idx + 1}`;
				select.appendChild(option);
			});
		} catch (err) {
			console.error("Cannot enumerate cameras:", err);
			document.getElementById('camera_select').innerHTML = '<option value="">Camera access denied</option>';
		}
	}

	enumerateCameras();

	// Start webcam
	window.startWebcam = async function() {
		const deviceId = document.getElementById('camera_select').value;
		const constraints = {
			video: deviceId ? { deviceId: { exact: deviceId } } : true,
			audio: false
		};

		try {
			webcamStream = await navigator.mediaDevices.getUserMedia(constraints);
			video.srcObject = webcamStream;

			video.onloadedmetadata = () => {
				overlayCanvas.width = video.videoWidth;
				overlayCanvas.height = video.videoHeight;
				overlayCanvas.style.width = video.clientWidth + 'px';
				overlayCanvas.style.height = video.clientHeight + 'px';
			};

			document.getElementById('btn_start_webcam').style.display = 'none';
			document.getElementById('btn_stop_webcam').style.display = '';
		} catch (err) {
			alert("Could not access webcam: " + err.message);
		}
	};

	// Stop webcam
	window.stopWebcam = function() {
		if (isPredicting) togglePrediction();

		if (webcamStream) {
			webcamStream.getTracks().forEach(t => t.stop());
			webcamStream = null;
		}
		video.srcObject = null;
		overlayCtx.clearRect(0, 0, overlayCanvas.width, overlayCanvas.height);

		document.getElementById('btn_start_webcam').style.display = '';
		document.getElementById('btn_stop_webcam').style.display = 'none';
	};

	// Toggle prediction loop
	window.togglePrediction = function() {
		const btn = document.getElementById('btn_toggle_predict');

		if (isPredicting) {
			isPredicting = false;
			if (predictionLoop) {
				clearInterval(predictionLoop);
				predictionLoop = null;
			}
			btn.innerHTML = '&#129302; Start Predictions';
			btn.classList.remove('active');
			overlayCtx.clearRect(0, 0, overlayCanvas.width, overlayCanvas.height);
			document.getElementById('prediction_info').textContent = 'Predictions stopped.';
		} else {
			const modelUuid = document.getElementById('webcam_model_select').value;
			if (modelUuid === 'none') {
				alert("Please select a model first.");
				return;
			}
			if (!webcamStream) {
				alert("Please start the webcam first.");
				return;
			}

			isPredicting = true;
			btn.innerHTML = '&#9209; Stop Predictions';
			btn.classList.add('active');

			loadWebcamModel(modelUuid).then(() => {
				const fps = parseInt(document.getElementById('webcam_fps').value) || 5;
				const interval = Math.round(1000 / fps);
				predictionLoop = setInterval(runWebcamPrediction, interval);
			});
		}
	};

	// Load model for webcam - with fix for Float32Array error
	async function loadWebcamModel(modelUuid) {
		document.getElementById('prediction_info').textContent = 'Loading model...';

		// Load labels
		try {
			const resp = await fetch('labels.php?model_uuid=' + encodeURIComponent(modelUuid));
			webcamLabels = await resp.json();
		} catch (e) {
			webcamLabels = [];
		}

		const modelJsonUrl = "get_model_file.php?uuid=" + encodeURIComponent(modelUuid) + "&filename=model.json";

		try {
			await tf.ready();
			if (webcamModel) {
				webcamModel.dispose();
				webcamModel = null;
			}

			// Fetch model.json first to check format and validate weight paths
			const modelJsonResp = await fetch(modelJsonUrl);
			if (!modelJsonResp.ok) {
				throw new Error("Failed to fetch model.json: HTTP " + modelJsonResp.status);
			}
			const modelJson = await modelJsonResp.json();

			// Determine if it's a graph model or layers model
			const isGraphModel = modelJson.modelTopology && modelJson.modelTopology.node;

			// Custom IOHandler to ensure binary weight files are fetched correctly
			// This fixes the Float32Array "byte length should be a multiple of 4" error
			// which occurs when weight .bin files are served with wrong content-type
			// or get corrupted by text-mode transfer
			const customIOHandler = {
				load: async function() {
					const weightsManifest = modelJson.weightsManifest;
					const weightSpecs = [];
					const weightDataArrays = [];

					for (const group of weightsManifest) {
						for (const spec of group.weights) {
							weightSpecs.push(spec);
						}
						for (const path of group.paths) {
							const weightUrl = "get_model_file.php?uuid=" + encodeURIComponent(modelUuid) + "&filename=" + encodeURIComponent(path);
							const weightResp = await fetch(weightUrl);
							if (!weightResp.ok) {
								throw new Error("Failed to fetch weight file: " + path + " (HTTP " + weightResp.status + ")");
							}
							const buffer = await weightResp.arrayBuffer();

							// Validate buffer length is multiple of 4
							if (buffer.byteLength % 4 !== 0) {
								console.warn("Weight file " + path + " has byte length " + buffer.byteLength + " (not multiple of 4). Padding...");
								const paddedLength = Math.ceil(buffer.byteLength / 4) * 4;
								const paddedBuffer = new ArrayBuffer(paddedLength);
								new Uint8Array(paddedBuffer).set(new Uint8Array(buffer));
								weightDataArrays.push(paddedBuffer);
							} else {
								weightDataArrays.push(buffer);
							}
						}
					}

					// Concatenate all weight buffers
					const totalLength = weightDataArrays.reduce((sum, buf) => sum + buf.byteLength, 0);
					const concatenated = new ArrayBuffer(totalLength);
					const view = new Uint8Array(concatenated);
					let offset = 0;
					for (const buf of weightDataArrays) {
						view.set(new Uint8Array(buf), offset);
						offset += buf.byteLength;
					}

					return {
						modelTopology: modelJson.modelTopology,
						weightSpecs: weightSpecs,
						weightData: concatenated,
						format: modelJson.format,
						generatedBy: modelJson.generatedBy,
						convertedBy: modelJson.convertedBy
					};
				}
			};

			if (isGraphModel) {
				webcamModel = await tf.loadGraphModel(customIOHandler);
			} else {
				webcamModel = await tf.loadLayersModel(customIOHandler);
			}

			document.getElementById('prediction_info').textContent = 'Model loaded. Running predictions...';
		} catch (e) {
			console.error("Model loading error:", e);
			document.getElementById('prediction_info').textContent = 'Error loading model: ' + e.message;
			isPredicting = false;
			document.getElementById('btn_toggle_predict').innerHTML = '&#129302; Start Predictions';
			document.getElementById('btn_toggle_predict').classList.remove('active');
		}
	}

	// Run single prediction frame
	async function runWebcamPrediction() {
		if (!isPredicting || !webcamModel || !webcamStream) return;
		if (video.readyState < 2) return;

		const confThreshold = parseFloat(document.getElementById('webcam_conf_slider').value);

		let shape;
		if (webcamModel.inputs && webcamModel.inputs[0]) {
			shape = webcamModel.inputs[0].shape.slice(1, 3);
		} else if (webcamModel.input && webcamModel.input.shape) {
			shape = webcamModel.input.shape.slice(1, 3);
		}

		if (!shape || shape.length < 2 || !shape[0] || !shape[1]) {
			// Fallback to 640x640
			shape = [640, 640];
		}

		const [modelHeight, modelWidth] = shape;

		const startTime = performance.now();

		// Run inference
		const inputTensor = tf.tidy(() => {
			return tf.browser.fromPixels(video)
				.resizeBilinear([modelHeight, modelWidth])
				.div(255)
				.expandDims();
		});

		let output;
		try {
			if (webcamModel.execute) {
				output = await webcamModel.execute(inputTensor);
			} else {
				output = await webcamModel.predict(inputTensor);
			}
		} catch (e) {
			inputTensor.dispose();
			console.error("Inference error:", e);
			return;
		}

		inputTensor.dispose();

		let res;
		if (output instanceof tf.Tensor) {
			res = output.arraySync();
			output.dispose();
		} else if (Array.isArray(output)) {
			res = output[0].arraySync();
			output.forEach(t => t.dispose());
		} else {
			res = output;
		}

		const detections = processWebcamOutput(res, modelWidth, modelHeight, confThreshold);
		const elapsed = (performance.now() - startTime).toFixed(1);

		drawDetections(detections, elapsed);
	}

	function processWebcamOutput(res, modelWidth, modelHeight, confThreshold) {
		let rawTensor;
		if (Array.isArray(res) && Array.isArray(res[0]) && Array.isArray(res[0][0])) {
			rawTensor = tf.tensor3d(res);
		} else {
			return [];
		}

		let shape = rawTensor.shape;
		let features = shape[1];
		let candidates = shape[2];

		if (features > candidates) {
			let transposed = rawTensor.transpose([0, 2, 1]);
			rawTensor.dispose();
			rawTensor = transposed;
			[features, candidates] = [candidates, features];
		}

		const numClasses = features - 4;
		if (numClasses <= 0) {
			rawTensor.dispose();
			return [];
		}

		let predTensor = rawTensor.transpose([0, 2, 1]);
		const [rawBoxes, scores] = tf.split(predTensor, [4, numClasses], 2);
		const boxes = rawBoxes.squeeze();
		const scoresSqueezed = scores.squeeze();

		const boxesArr = boxes.arraySync();
		const scoresArr = scoresSqueezed.arraySync();

		const detections = [];

		for (let i = 0; i < boxesArr.length; i++) {
			const classScores = numClasses === 1 ? [scoresArr[i]] : scoresArr[i];
			let bestScore = 0;
			let bestClass = -1;

			if (Array.isArray(classScores)) {
				for (let c = 0; c < classScores.length; c++) {
					if (classScores[c] > bestScore) {
						bestScore = classScores[c];
						bestClass = c;
					}
				}
			} else {
				bestScore = classScores;
				bestClass = 0;
			}

			if (bestScore < confThreshold) continue;

			const [cx, cy, w, h] = boxesArr[i];
			const isPixel = cx > 2.0 || cy > 2.0;

			let xMin, yMin, xMax, yMax;
			if (isPixel) {
				xMin = (cx - w / 2) / modelWidth;
				yMin = (cy - h / 2) / modelHeight;
				xMax = (cx + w / 2) / modelWidth;
				yMax = (cy + h / 2) / modelHeight;
			} else {
				xMin = cx - w / 2;
				yMin = cy - h / 2;
				xMax = cx + w / 2;
				yMax = cy + h / 2;
			}

			const label = (webcamLabels && webcamLabels[bestClass]) ? webcamLabels[bestClass] : `class_${bestClass}`;

			detections.push({
				xMin: Math.max(0, xMin),
				yMin: Math.max(0, yMin),
				xMax: Math.min(1, xMax),
				yMax: Math.min(1, yMax),
				score: bestScore,
				label: label
			});
		}

		const nmsDetections = simpleNMS(detections, 0.5);

		rawTensor.dispose();
		predTensor.dispose();
		rawBoxes.dispose();
		scores.dispose();
		boxes.dispose();
		scoresSqueezed.dispose();

		return nmsDetections;
	}

	function simpleNMS(detections, iouThresh) {
		detections.sort((a, b) => b.score - a.score);
		const kept = [];

		for (let i = 0; i < detections.length; i++) {
			let dominated = false;
			for (let j = 0; j < kept.length; j++) {
				if (computeIoU(detections[i], kept[j]) > iouThresh) {
					dominated = true;
					break;
				}
			}
			if (!dominated) kept.push(detections[i]);
		}
		return kept;
	}

	function computeIoU(a, b) {
		const x1 = Math.max(a.xMin, b.xMin);
		const y1 = Math.max(a.yMin, b.yMin);
		const x2 = Math.min(a.xMax, b.xMax);
		const y2 = Math.min(a.yMax, b.yMax);
		const inter = Math.max(0, x2 - x1) * Math.max(0, y2 - y1);
		const areaA = (a.xMax - a.xMin) * (a.yMax - a.yMin);
		const areaB = (b.xMax - b.xMin) * (b.yMax - b.yMin);
		return inter / (areaA + areaB - inter);
	}

	function drawDetections(detections, elapsed) {
		const w = overlayCanvas.width;
		const h = overlayCanvas.height;

		overlayCtx.clearRect(0, 0, w, h);

		for (const det of detections) {
			const x = det.xMin * w;
			const y = det.yMin * h;
			const bw = (det.xMax - det.xMin) * w;
			const bh = (det.yMax - det.yMin) * h;

			overlayCtx.strokeStyle = '#00ff88';
			overlayCtx.lineWidth = 2;
			overlayCtx.strokeRect(x, y, bw, bh);

			const text = `${det.label} ${(det.score * 100).toFixed(0)}%`;
			overlayCtx.font = 'bold 13px monospace';
			const textWidth = overlayCtx.measureText(text).width;

			overlayCtx.fillStyle = 'rgba(0, 255, 100, 0.85)';
			overlayCtx.fillRect(x, y - 18, textWidth + 6, 18);

			overlayCtx.fillStyle = '#000';
			overlayCtx.fillText(text, x + 3, y - 4);
		}

		const infoText = `Detections: ${detections.length} | Inference: ${elapsed}ms | ` +
			detections.map(d => `${d.label}(${(d.score*100).toFixed(0)}%)`).join(', ');
		document.getElementById('prediction_info').textContent = infoText;
	}

	// Capture frame and auto-upload
	window.captureFrame = function() {
		if (!webcamStream || video.readyState < 2) {
			alert("Webcam is not running.");
			return;
		}

		captureCanvas.width = video.videoWidth;
		captureCanvas.height = video.videoHeight;
		captureCtx.drawImage(video, 0, 0);

		capturedCount++;
		const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
		const filename = `webcam_${timestamp}.jpg`;

		captureCanvas.toBlob(function(blob) {
			const url = URL.createObjectURL(blob);

			const container = document.getElementById('captured_images');
			const thumb = document.createElement('div');
			thumb.className = 'captured-thumb';
			thumb.innerHTML = `
				<img src="${url}" alt="${filename}">
				<span class="upload-status uploading">Uploading...</span>
				<div class="thumb-actions">
					<button onclick="this.closest('.captured-thumb').remove()" style="background:#f38ba8; color:#1e1e2e;">&#10005;</button>
				</div>
			`;
			container.prepend(thumb);

			// Auto-upload immediately
			autoUpload(thumb, blob, filename);

		}, 'image/jpeg', 0.92);
	};

	// Auto upload function
	async function autoUpload(thumb, blob, filename) {
		const statusEl = thumb.querySelector('.upload-status');

		const formData = new FormData();
		formData.append('image', blob, filename);

		try {
			const response = await fetch('upload_image.php', {
				method: 'POST',
				body: formData
			});
			const result = await response.text();

			if (result.includes("Error:")) {
				statusEl.textContent = 'Upload failed';
				statusEl.className = 'upload-status error';
				console.error("Upload failed:", result);
			} else {
				statusEl.textContent = '✓ Uploaded';
				statusEl.className = 'upload-status';
			}
		} catch (err) {
			statusEl.textContent = 'Error';
			statusEl.className = 'upload-status error';
			console.error("Upload error:", err.message);
		}
	}

	// Confidence slider display
	document.getElementById('webcam_conf_slider').addEventListener('input', function() {
		document.getElementById('webcam_conf_value').textContent = parseFloat(this.value).toFixed(2);
	});

})();
</script>

<?php
	include_once("footer.php");
?>
