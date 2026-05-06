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
		background: #11111b;
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
		transition: background 0.2s, opacity 0.2s;
	}

	.webcam-controls button:disabled {
		opacity: 0.5;
		cursor: not-allowed;
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

	#webcam_toast {
		position: fixed;
		bottom: 24px;
		left: 50%;
		transform: translateX(-50%) translateY(80px);
		background: #313244;
		color: #cdd6f4;
		padding: 10px 20px;
		border-radius: 8px;
		font-size: 13px;
		font-weight: 500;
		box-shadow: 0 4px 16px rgba(0,0,0,0.4);
		z-index: 9999;
		opacity: 0;
		transition: opacity 0.3s, transform 0.3s;
		pointer-events: none;
	}

	#webcam_toast.visible {
		opacity: 1;
		transform: translateX(-50%) translateY(0);
	}

	#webcam_toast.error {
		border-left: 4px solid #f38ba8;
	}

	#webcam_toast.success {
		border-left: 4px solid #a6e3a1;
	}

	#webcam_toast.info {
		border-left: 4px solid #89b4fa;
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

<div id="webcam_toast"></div>

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
	let currentModelUuid = null;
	let isStartingWebcam = false;
	let isLoadingModel = false;
	let toastTimeout = null;

	const video = document.getElementById('webcam_video');
	const overlayCanvas = document.getElementById('webcam_canvas');
	const overlayCtx = overlayCanvas.getContext('2d');
	const captureCanvas = document.getElementById('capture_canvas');
	const captureCtx = captureCanvas.getContext('2d');

	// ─── Toast notification (replaces alerts) ───────────────────────────
	function showToast(message, type, duration) {
		type = type || 'info';
		duration = duration || 3000;
		const toast = document.getElementById('webcam_toast');
		if (!toast) return;

		if (toastTimeout) {
			clearTimeout(toastTimeout);
			toastTimeout = null;
		}

		toast.textContent = message;
		toast.className = 'visible ' + type;

		toastTimeout = setTimeout(function() {
			toast.className = '';
			toastTimeout = null;
		}, duration);
	}

	// ─── Button state helpers ───────────────────────────────────────────
	function setButtonLoading(btnId, loading, loadingText) {
		const btn = document.getElementById(btnId);
		if (!btn) return;
		btn.disabled = loading;
		if (loading && loadingText) {
			btn.dataset.originalText = btn.innerHTML;
			btn.innerHTML = loadingText;
		} else if (!loading && btn.dataset.originalText) {
			btn.innerHTML = btn.dataset.originalText;
			delete btn.dataset.originalText;
		}
	}

	// ─── Check if webcam is active and video is ready ───────────────────
	function isWebcamReady() {
		return webcamStream !== null && video.srcObject !== null && video.readyState >= 2;
	}

	function isWebcamActive() {
		return webcamStream !== null && video.srcObject !== null;
	}

	// ─── Enumerate cameras ──────────────────────────────────────────────
	async function enumerateCameras() {
		const select = document.getElementById('camera_select');
		try {
			// Request temporary access to get labels
			let tempStream = null;
			try {
				tempStream = await navigator.mediaDevices.getUserMedia({ video: true });
			} catch (permErr) {
				// User denied or no camera — handle gracefully
				select.innerHTML = '<option value="">No camera access</option>';
				showToast("Camera permission denied. Please allow camera access.", "error", 5000);
				return;
			}

			if (tempStream) {
				tempStream.getTracks().forEach(function(t) { t.stop(); });
			}

			const devices = await navigator.mediaDevices.enumerateDevices();
			const videoDevices = devices.filter(function(d) { return d.kind === 'videoinput'; });
			select.innerHTML = '';

			if (videoDevices.length === 0) {
				select.innerHTML = '<option value="">No cameras found</option>';
				showToast("No cameras detected on this device.", "error", 4000);
				return;
			}

			videoDevices.forEach(function(device, idx) {
				const option = document.createElement('option');
				option.value = device.deviceId;
				option.textContent = device.label || ('Camera ' + (idx + 1));
				select.appendChild(option);
			});
		} catch (err) {
			console.error("Cannot enumerate cameras:", err);
			select.innerHTML = '<option value="">Camera error</option>';
			showToast("Could not detect cameras: " + (err.message || "Unknown error"), "error", 5000);
		}
	}

	enumerateCameras();

	// ─── Start webcam (returns a promise so other functions can await it) ─
	window.startWebcam = async function() {
		// Prevent double-start
		if (isStartingWebcam) return true;
		if (isWebcamActive()) return true;

		isStartingWebcam = true;
		setButtonLoading('btn_start_webcam', true, '&#9654; Starting...');
		setButtonLoading('btn_toggle_predict', true);
		setButtonLoading('btn_capture', true);

		const deviceId = document.getElementById('camera_select').value;

		if (!deviceId && document.getElementById('camera_select').options.length > 0) {
			// No device selected but options exist — just use default
		}

		const constraints = {
			video: deviceId ? { deviceId: { exact: deviceId } } : true,
			audio: false
		};

		try {
			webcamStream = await navigator.mediaDevices.getUserMedia(constraints);
			video.srcObject = webcamStream;

			// Wait for video to actually be ready
			await new Promise(function(resolve, reject) {
				let resolved = false;

				video.onloadedmetadata = function() {
					if (!resolved) {
						resolved = true;
						resolve();
					}
				};

				// Timeout safety — if metadata never fires
				setTimeout(function() {
					if (!resolved) {
						resolved = true;
						resolve(); // resolve anyway, we'll check readyState later
					}
				}, 5000);
			});

			// Wait a tiny bit more for readyState to settle
			await new Promise(function(resolve) {
				if (video.readyState >= 2) {
					resolve();
					return;
				}
				video.oncanplay = function() { resolve(); };
				setTimeout(resolve, 2000); // safety timeout
			});

			// Set overlay canvas dimensions
			if (video.videoWidth > 0 && video.videoHeight > 0) {
				overlayCanvas.width = video.videoWidth;
				overlayCanvas.height = video.videoHeight;
				overlayCanvas.style.width = video.clientWidth + 'px';
				overlayCanvas.style.height = video.clientHeight + 'px';
			}

			document.getElementById('btn_start_webcam').style.display = 'none';
			document.getElementById('btn_stop_webcam').style.display = '';
			showToast("Webcam started", "success", 2000);

			isStartingWebcam = false;
			setButtonLoading('btn_start_webcam', false);
			setButtonLoading('btn_toggle_predict', false);
			setButtonLoading('btn_capture', false);
			return true;

		} catch (err) {
			console.error("Webcam start error:", err);
			webcamStream = null;
			video.srcObject = null;

			let msg = "Could not access webcam.";
			if (err.name === 'NotAllowedError') {
				msg = "Camera permission was denied. Please allow access in your browser settings.";
			} else if (err.name === 'NotFoundError') {
				msg = "No camera found. Please connect a camera and try again.";
			} else if (err.name === 'NotReadableError') {
				msg = "Camera is in use by another application.";
			} else if (err.message) {
				msg += " " + err.message;
			}

			showToast(msg, "error", 6000);

			isStartingWebcam = false;
			setButtonLoading('btn_start_webcam', false);
			setButtonLoading('btn_toggle_predict', false);
			setButtonLoading('btn_capture', false);
			return false;
		}
	};

	// ─── Stop webcam ────────────────────────────────────────────────────
	window.stopWebcam = function() {
		// Stop predictions first if running
		if (isPredicting) {
			stopPredictionLoop();
		}

		if (webcamStream) {
			try {
				webcamStream.getTracks().forEach(function(t) { t.stop(); });
			} catch (e) {
				console.warn("Error stopping tracks:", e);
			}
			webcamStream = null;
		}

		video.srcObject = null;

		try {
			overlayCtx.clearRect(0, 0, overlayCanvas.width, overlayCanvas.height);
		} catch (e) { /* ignore */ }

		document.getElementById('btn_start_webcam').style.display = '';
		document.getElementById('btn_stop_webcam').style.display = 'none';
		document.getElementById('prediction_info').textContent = 'Webcam stopped.';
		showToast("Webcam stopped", "info", 2000);
	};

	// ─── Internal: stop prediction loop cleanly ─────────────────────────
	function stopPredictionLoop() {
		isPredicting = false;
		if (predictionLoop) {
			clearInterval(predictionLoop);
			predictionLoop = null;
		}
		const btn = document.getElementById('btn_toggle_predict');
		btn.innerHTML = '&#129302; Start Predictions';
		btn.classList.remove('active');

		try {
			overlayCtx.clearRect(0, 0, overlayCanvas.width, overlayCanvas.height);
		} catch (e) { /* ignore */ }

		document.getElementById('prediction_info').textContent = 'Predictions stopped.';
	}

	// ─── Toggle prediction loop ─────────────────────────────────────────
	window.togglePrediction = async function() {
		const btn = document.getElementById('btn_toggle_predict');

		// If currently predicting, just stop
		if (isPredicting) {
			stopPredictionLoop();
			showToast("Predictions stopped", "info", 2000);
			return;
		}

		// ── Starting predictions ──

		// Check model selection first (fast check, no async needed)
		const modelUuid = document.getElementById('webcam_model_select').value;
		if (modelUuid === 'none') {
			showToast("Please select a model before starting predictions.", "error", 4000);
			return;
		}

		// Disable button while we set things up
		setButtonLoading('btn_toggle_predict', true, '&#129302; Starting...');

		// If webcam is not running, start it automatically
		if (!isWebcamActive()) {
			showToast("Starting webcam...", "info", 2000);
			const webcamOk = await startWebcam();
			if (!webcamOk) {
				setButtonLoading('btn_toggle_predict', false);
				btn.innerHTML = '&#129302; Start Predictions';
				showToast("Cannot start predictions — webcam failed to start.", "error", 4000);
				return;
			}
		}

		// Double-check webcam is actually streaming
		if (!webcamStream || !video.srcObject) {
			setButtonLoading('btn_toggle_predict', false);
			btn.innerHTML = '&#129302; Start Predictions';
			showToast("Webcam is not available. Please try starting it manually.", "error", 4000);
			return;
		}

		// Wait for video readyState if needed (up to 3 seconds)
		if (video.readyState < 2) {
			document.getElementById('prediction_info').textContent = 'Waiting for webcam feed...';
			const ready = await waitForVideoReady(3000);
			if (!ready) {
				setButtonLoading('btn_toggle_predict', false);
				btn.innerHTML = '&#129302; Start Predictions';
				showToast("Webcam feed not ready. Please try again.", "error", 4000);
				return;
			}
		}

		// Load model (or reuse if same model already loaded)
		const modelOk = await loadWebcamModel(modelUuid);
		if (!modelOk) {
			setButtonLoading('btn_toggle_predict', false);
			btn.innerHTML = '&#129302; Start Predictions';
			return; // Error toast already shown in loadWebcamModel
		}

		// Everything ready — start the loop
		isPredicting = true;
		btn.innerHTML = '&#9209; Stop Predictions';
		btn.classList.add('active');
		setButtonLoading('btn_toggle_predict', false);

		const fps = Math.max(1, Math.min(30, parseInt(document.getElementById('webcam_fps').value) || 5));
		const interval = Math.round(1000 / fps);
		predictionLoop = setInterval(runWebcamPrediction, interval);

		showToast("Predictions running at " + fps + " FPS", "success", 2500);
	};

	// ─── Helper: wait for video to be ready ─────────────────────────────
	function waitForVideoReady(timeoutMs) {
		return new Promise(function(resolve) {
			if (video.readyState >= 2) {
				resolve(true);
				return;
			}

			let resolved = false;

			function onReady() {
				if (!resolved) {
					resolved = true;
					video.removeEventListener('canplay', onReady);
					resolve(true);
				}
			}

			video.addEventListener('canplay', onReady);

			setTimeout(function() {
				if (!resolved) {
					resolved = true;
					video.removeEventListener('canplay', onReady);
					resolve(video.readyState >= 2);
				}
			}, timeoutMs || 3000);
		});
	}

	// ─── Load model ─────────────────────────────────────────────────────
	async function loadWebcamModel(modelUuid) {
		// If same model already loaded, skip
		if (webcamModel && currentModelUuid === modelUuid) {
			document.getElementById('prediction_info').textContent = 'Model ready. Running predictions...';
			return true;
		}

		if (isLoadingModel) {
			showToast("Model is already loading, please wait...", "info", 2000);
			return false;
		}

		isLoadingModel = true;
		document.getElementById('prediction_info').textContent = 'Loading model...';

		// Load labels
		try {
			const resp = await fetch('labels.php?model_uuid=' + encodeURIComponent(modelUuid));
			if (resp.ok) {
				webcamLabels = await resp.json();
			} else {
				webcamLabels = [];
				console.warn("Labels fetch returned status:", resp.status);
			}
		} catch (e) {
			webcamLabels = [];
			console.warn("Could not load labels:", e);
		}

		// Ensure labels is always an array
		if (!Array.isArray(webcamLabels)) {
			webcamLabels = [];
		}

		const modelJsonUrl = "get_model_file.php?&uuid=" + encodeURIComponent(modelUuid) + "&filename=model.json";

		try {
			// Check if TensorFlow.js is available
			if (typeof tf === 'undefined') {
				throw new Error("TensorFlow.js is not loaded. Please refresh the page.");
			}

			await tf.ready();

			// Dispose old model if exists
			if (webcamModel) {
				try {
					webcamModel.dispose();
				} catch (e) {
					console.warn("Error disposing old model:", e);
				}
				webcamModel = null;
				currentModelUuid = null;
			}

			webcamModel = await tf.loadGraphModel(modelJsonUrl, {
				onProgress: function(p) {
					const percent = (p * 100).toFixed(0);
					document.getElementById('prediction_info').textContent = 'Loading model... ' + percent + '%';
				}
			});

			currentModelUuid = modelUuid;
			document.getElementById('prediction_info').textContent = 'Model loaded. Running predictions...';
			isLoadingModel = false;
			return true;

		} catch (e) {
			console.error("Model loading error:", e);
			document.getElementById('prediction_info').textContent = 'Error loading model.';
			webcamModel = null;
			currentModelUuid = null;
			isLoadingModel = false;

			let msg = "Failed to load model.";
			if (e.message) {
				if (e.message.includes("404") || e.message.includes("not found")) {
					msg = "Model file not found. Please check that the model is exported.";
				} else if (e.message.includes("fetch")) {
					msg = "Network error loading model. Please check your connection.";
				} else {
					msg = "Model error: " + e.message.substring(0, 100);
				}
			}
			showToast(msg, "error", 6000);
			return false;
		}
	}

	// ─── Run single prediction frame ────────────────────────────────────
	async function runWebcamPrediction() {
		// Guard: bail out if state is invalid
		if (!isPredicting) return;
		if (!webcamModel) return;
		if (!webcamStream) return;
		if (video.readyState < 2) return;

		const confThreshold = parseFloat(document.getElementById('webcam_conf_slider').value) || 0.3;

		let shape;
		try {
			if (webcamModel.inputs && webcamModel.inputs[0] && webcamModel.inputs[0].shape) {
				shape = webcamModel.inputs[0].shape.slice(1, 3);
			}
		} catch (e) {
			shape = null;
		}

		if (!shape || shape.length < 2 || !shape[0] || !shape[1]) {
			shape = [640, 640];
		}

		const modelHeight = shape[0];
		const modelWidth = shape[1];

		const startTime = performance.now();

		let inputTensor = null;
		let output = null;

		try {
			inputTensor = tf.tidy(function() {
				return tf.browser.fromPixels(video)
					.resizeBilinear([modelHeight, modelWidth])
					.div(255)
					.expandDims();
			});
		} catch (e) {
			console.error("Frame capture error:", e);
			if (inputTensor) { try { inputTensor.dispose(); } catch(x){} }
			return;
		}

		try {
			output = await webcamModel.execute(inputTensor);
		} catch (e) {
			if (inputTensor) { try { inputTensor.dispose(); } catch(x){} }
			console.error("Inference error:", e);
			return;
		}

		if (inputTensor) { try { inputTensor.dispose(); } catch(x){} }

		let res;
		try {
			if (output instanceof tf.Tensor) {
				res = output.arraySync();
				output.dispose();
			} else if (Array.isArray(output)) {
				res = output[0].arraySync();
				output.forEach(function(t) { if (t && t.dispose) t.dispose(); });
			} else {
				res = output;
			}
		} catch (e) {
			console.error("Output processing error:", e);
			if (output) {
				try {
					if (output instanceof tf.Tensor) output.dispose();
					else if (Array.isArray(output)) output.forEach(function(t) { if (t && t.dispose) t.dispose(); });
				} catch(x){}
			}
			return;
		}

		let detections = [];
		try {
			detections = processWebcamOutput(res, modelWidth, modelHeight, confThreshold);
		} catch (e) {
			console.error("Post-processing error:", e);
			detections = [];
		}

		const elapsed = (performance.now() - startTime).toFixed(1);
		drawDetections(detections, elapsed);
	}

	// ─── Process model output ───────────────────────────────────────────
	function processWebcamOutput(res, modelWidth, modelHeight, confThreshold) {
		if (!res || !Array.isArray(res) || !Array.isArray(res[0]) || !Array.isArray(res[0][0])) {
			return [];
		}

		let rawTensor = null;
		let predTensor = null;
		let rawBoxes = null;
		let scores = null;
		let boxes = null;
		let scoresSqueezed = null;

		try {
			rawTensor = tf.tensor3d(res);

			let shape = rawTensor.shape;
			let features = shape[1];
			let candidates = shape[2];

			if (features > candidates) {
				let transposed = rawTensor.transpose([0, 2, 1]);
				rawTensor.dispose();
				rawTensor = transposed;
				features = shape[2];
				candidates = shape[1];
			}

			const numClasses = features - 4;
			if (numClasses <= 0) {
				rawTensor.dispose();
				return [];
			}

			predTensor = rawTensor.transpose([0, 2, 1]);
			const splits = tf.split(predTensor, [4, numClasses], 2);
			rawBoxes = splits[0];
			scores = splits[1];
			boxes = rawBoxes.squeeze();
			scoresSqueezed = scores.squeeze();

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

				const cx = boxesArr[i][0];
				const cy = boxesArr[i][1];
				const w = boxesArr[i][2];
				const h = boxesArr[i][3];
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

				const label = (webcamLabels && webcamLabels[bestClass]) ? webcamLabels[bestClass] : ('class_' + bestClass);

				detections.push({
					xMin: Math.max(0, xMin),
					yMin: Math.max(0, yMin),
					xMax: Math.min(1, xMax),
					yMax: Math.min(1, yMax),
					score: bestScore,
					label: label
				});
			}

			// Cleanup tensors
			rawTensor.dispose();
			predTensor.dispose();
			rawBoxes.dispose();
			scores.dispose();
			boxes.dispose();
			scoresSqueezed.dispose();

			// Apply NMS
			const nmsDetections = simpleNMS(detections, 0.5);
			return nmsDetections;

		} catch (e) {
			console.error("processWebcamOutput error:", e);
			// Cleanup any tensors that might still be alive
			if (rawTensor) { try { rawTensor.dispose(); } catch(x){} }
			if (predTensor) { try { predTensor.dispose(); } catch(x){} }
			if (rawBoxes) { try { rawBoxes.dispose(); } catch(x){} }
			if (scores) { try { scores.dispose(); } catch(x){} }
			if (boxes) { try { boxes.dispose(); } catch(x){} }
			if (scoresSqueezed) { try { scoresSqueezed.dispose(); } catch(x){} }
			return [];
		}
	}

	// ─── Simple NMS ─────────────────────────────────────────────────────
	function simpleNMS(detections, iouThresh) {
		if (!detections || detections.length === 0) return [];

		detections.sort(function(a, b) { return b.score - a.score; });
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
		const union = areaA + areaB - inter;
		if (union <= 0) return 0;
		return inter / union;
	}

	// ─── Draw detections on overlay canvas ──────────────────────────────
	function drawDetections(detections, elapsed) {
		const w = overlayCanvas.width;
		const h = overlayCanvas.height;

		try {
			overlayCtx.clearRect(0, 0, w, h);
		} catch (e) { return; }

		if (!detections || detections.length === 0) {
			document.getElementById('prediction_info').textContent =
				'No detections | Inference: ' + elapsed + 'ms';
			return;
		}

		for (let i = 0; i < detections.length; i++) {
			var det = detections[i];
			var x = det.xMin * w;
			var y = det.yMin * h;
			var bw = (det.xMax - det.xMin) * w;
			var bh = (det.yMax - det.yMin) * h;

			overlayCtx.strokeStyle = '#00ff88';
			overlayCtx.lineWidth = 2;
			overlayCtx.strokeRect(x, y, bw, bh);

			var text = det.label + ' ' + (det.score * 100).toFixed(0) + '%';
			overlayCtx.font = 'bold 13px monospace';
			var textWidth = overlayCtx.measureText(text).width;

			overlayCtx.fillStyle = 'rgba(0, 255, 100, 0.85)';
			overlayCtx.fillRect(x, y - 18, textWidth + 6, 18);

			overlayCtx.fillStyle = '#000';
			overlayCtx.fillText(text, x + 3, y - 4);
		}

		var infoText = 'Detections: ' + detections.length + ' | Inference: ' + elapsed + 'ms | ' +
			detections.map(function(d) { return d.label + '(' + (d.score * 100).toFixed(0) + '%)'; }).join(', ');
		document.getElementById('prediction_info').textContent = infoText;
	}

	// ─── Capture frame and auto-upload ──────────────────────────────────
	window.captureFrame = async function() {
		// If webcam is not running, start it first
		if (!isWebcamActive()) {
			showToast("Starting webcam for capture...", "info", 2000);
			setButtonLoading('btn_capture', true, '&#128248; Starting...');
			var webcamOk = await startWebcam();
			if (!webcamOk) {
				setButtonLoading('btn_capture', false);
				showToast("Cannot capture — webcam failed to start.", "error", 4000);
				return;
			}
			// Give the camera a moment to produce a real frame
			await new Promise(function(resolve) { setTimeout(resolve, 500); });
		}

		// Wait for video to be ready
		if (video.readyState < 2) {
			var ready = await waitForVideoReady(3000);
			if (!ready) {
				showToast("Webcam feed not ready for capture. Please try again.", "error", 3000);
				return;
			}
		}

		// Validate video dimensions
		if (!video.videoWidth || !video.videoHeight) {
			showToast("Webcam not producing frames yet. Please wait a moment.", "error", 3000);
			return;
		}

		setButtonLoading('btn_capture', true, '&#128248; Capturing...');

		try {
			captureCanvas.width = video.videoWidth;
			captureCanvas.height = video.videoHeight;
			captureCtx.drawImage(video, 0, 0);
		} catch (e) {
			console.error("Capture draw error:", e);
			setButtonLoading('btn_capture', false);
			showToast("Failed to capture frame from webcam.", "error", 3000);
			return;
		}

		capturedCount++;
		var timestamp = new Date().toISOString().replace(/[:.]/g, '-');
		var filename = 'webcam_' + timestamp + '.jpg';

		try {
			captureCanvas.toBlob(function(blob) {
				if (!blob) {
					setButtonLoading('btn_capture', false);
					showToast("Failed to encode frame as image.", "error", 3000);
					return;
				}

				var url;
				try {
					url = URL.createObjectURL(blob);
				} catch (e) {
					setButtonLoading('btn_capture', false);
					showToast("Failed to create image preview.", "error", 3000);
					return;
				}

				var container = document.getElementById('captured_images');
				var thumb = document.createElement('div');
				thumb.className = 'captured-thumb';
				thumb.innerHTML =
					'<img src="' + url + '" alt="' + filename + '">' +
					'<span class="upload-status uploading">Uploading...</span>' +
					'<div class="thumb-actions">' +
						'<button onclick="this.closest(\'.captured-thumb\').remove()" style="background:#f38ba8; color:#1e1e2e;">&#10005;</button>' +
					'</div>';
				container.prepend(thumb);

				// Auto-upload immediately
				autoUpload(thumb, blob, filename);

				setButtonLoading('btn_capture', false);
				showToast("Frame captured and uploading...", "success", 2000);

			}, 'image/jpeg', 0.92);
		} catch (e) {
			console.error("toBlob error:", e);
			setButtonLoading('btn_capture', false);
			showToast("Failed to process captured frame.", "error", 3000);
		}
	};

	// ─── Auto upload function ───────────────────────────────────────────
	async function autoUpload(thumb, blob, filename) {
		var statusEl = thumb.querySelector('.upload-status');
		if (!statusEl) return;

		var formData = new FormData();
		formData.append('image', blob, filename);

		try {
			var response = await fetch('upload_image.php', {
				method: 'POST',
				body: formData
			});

			if (!response.ok) {
				throw new Error("Server returned " + response.status);
			}

			var result = await response.text();

			if (result.indexOf("Error:") !== -1) {
				statusEl.textContent = 'Upload failed';
				statusEl.className = 'upload-status error';
				console.error("Upload failed:", result);
				showToast("Upload failed: " + result.substring(0, 80), "error", 4000);
			} else {
				statusEl.textContent = '\u2713 Uploaded';
				statusEl.className = 'upload-status';

				// Refresh the dynamic content (image list sidebar, home ribbon, etc.)
				if (typeof load_dynamic_content === 'function') {
					try { load_dynamic_content(); } catch (e) { /* ignore */ }
				}
			}
		} catch (err) {
			statusEl.textContent = 'Error';
			statusEl.className = 'upload-status error';
			console.error("Upload error:", err);
			showToast("Upload error: " + (err.message || "Network failure"), "error", 4000);
		}
	}

	// ─── Confidence slider display ──────────────────────────────────────
	var confSlider = document.getElementById('webcam_conf_slider');
	if (confSlider) {
		confSlider.addEventListener('input', function() {
			var display = document.getElementById('webcam_conf_value');
			if (display) {
				display.textContent = parseFloat(this.value).toFixed(2);
			}
		});
	}

	// ─── Handle camera change while running ─────────────────────────────
	var cameraSelect = document.getElementById('camera_select');
	if (cameraSelect) {
		cameraSelect.addEventListener('change', async function() {
			// If webcam is currently active, restart with new camera
			if (isWebcamActive()) {
				var wasPredicting = isPredicting;
				if (isPredicting) {
					stopPredictionLoop();
				}

				// Stop current stream
				if (webcamStream) {
					try {
						webcamStream.getTracks().forEach(function(t) { t.stop(); });
					} catch (e) { /* ignore */ }
					webcamStream = null;
				}
				video.srcObject = null;

				// Reset button state so startWebcam works
				document.getElementById('btn_start_webcam').style.display = '';
				document.getElementById('btn_stop_webcam').style.display = 'none';

				showToast("Switching camera...", "info", 2000);

				var ok = await startWebcam();
				if (ok && wasPredicting) {
					// Restart predictions with new camera
					await togglePrediction();
				}
			}
		});
	}

	// ─── Handle model change while predicting ───────────────────────────
	var modelSelect = document.getElementById('webcam_model_select');
	if (modelSelect) {
		modelSelect.addEventListener('change', async function() {
			if (isPredicting) {
				// Stop current predictions, user can restart
				stopPredictionLoop();
				showToast("Model changed. Press Start Predictions to use the new model.", "info", 3500);
			}
		});
	}

	// ─── Handle FPS change while predicting ─────────────────────────────
	var fpsInput = document.getElementById('webcam_fps');
	if (fpsInput) {
		fpsInput.addEventListener('change', function() {
			if (isPredicting && predictionLoop) {
				clearInterval(predictionLoop);
				var fps = Math.max(1, Math.min(30, parseInt(this.value) || 5));
				var interval = Math.round(1000 / fps);
				predictionLoop = setInterval(runWebcamPrediction, interval);
				showToast("FPS updated to " + fps, "info", 1500);
			}
		});
	}

	// ─── Resize overlay canvas when video resizes ───────────────────────
	var resizeObserver = null;
	try {
		resizeObserver = new ResizeObserver(function() {
			if (video.clientWidth > 0 && video.clientHeight > 0) {
				overlayCanvas.style.width = video.clientWidth + 'px';
				overlayCanvas.style.height = video.clientHeight + 'px';
			}
		});
		resizeObserver.observe(video);
	} catch (e) {
		// ResizeObserver not supported — fallback with window resize
		window.addEventListener('resize', function() {
			if (video.clientWidth > 0 && video.clientHeight > 0) {
				overlayCanvas.style.width = video.clientWidth + 'px';
				overlayCanvas.style.height = video.clientHeight + 'px';
			}
		});
	}

	// ─── Cleanup on page unload ─────────────────────────────────────────
	window.addEventListener('beforeunload', function() {
		if (isPredicting) {
			stopPredictionLoop();
		}
		if (webcamStream) {
			try {
				webcamStream.getTracks().forEach(function(t) { t.stop(); });
			} catch (e) { /* ignore */ }
		}
		if (webcamModel) {
			try { webcamModel.dispose(); } catch (e) { /* ignore */ }
		}
	});

})();
</script>

<?php
	include_once("footer.php");
?>
