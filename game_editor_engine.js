// ═══════════════════════════════════════════════════════════════════════════
// GAME ENGINE (inline, enhanced with show_text overlay support)
// ═══════════════════════════════════════════════════════════════════════════

(function() {
	"use strict";

	var gameRunning = false;
	var gameLoop = null;
	var webcamStream = null;
	var gameModel = null;
	var gameLabels = [];
	var currentModelUuid = null;
	var isLoadingModel = false;

	var video = document.getElementById('game_video');
	var overlayCanvas = document.getElementById('game_overlay_canvas');
	var overlayCtx = overlayCanvas ? overlayCanvas.getContext('2d') : null;
	var textOverlay = document.getElementById('game_text_overlay');
	var editor = document.getElementById('dsl_editor');
	var outputDiv = document.getElementById('game_output');
	var statusDiv = document.getElementById('game_status');

	// ─── Output helpers ─────────────────────────────────────────────────
	function appendOutput(text) {
		var timestamp = new Date().toLocaleTimeString();
		outputDiv.textContent += '[' + timestamp + '] ' + text + '\n';
		outputDiv.scrollTop = outputDiv.scrollHeight;
	}

	function clearOutput() {
		outputDiv.textContent = '';
	}

	function setStatus(text) {
		statusDiv.textContent = 'Status: ' + text;
	}

	// ─── Text overlay on video ──────────────────────────────────────────
	function showTextOnVideo(message, style) {
		if (!textOverlay) return;
		textOverlay.textContent = message;
		textOverlay.className = '';
		if (style && style !== 'normal') {
			textOverlay.classList.add(style);
		}
	}

	function clearTextOverlay() {
		if (textOverlay) {
			textOverlay.textContent = '';
			textOverlay.className = '';
		}
	}

	// ─── Camera enumeration ─────────────────────────────────────────────
	async function enumerateGameCameras() {
		var select = document.getElementById('game_camera_select');
		try {
			var tempStream = await navigator.mediaDevices.getUserMedia({ video: true });
			if (tempStream) tempStream.getTracks().forEach(function(t) { t.stop(); });
		} catch (e) {
			select.innerHTML = '<option value="">Kein Kamerazugriff</option>';
			return;
		}
		try {
			var devices = await navigator.mediaDevices.enumerateDevices();
			var videoDevices = devices.filter(function(d) { return d.kind === 'videoinput'; });
			select.innerHTML = '';
			videoDevices.forEach(function(device, idx) {
				var option = document.createElement('option');
				option.value = device.deviceId;
				option.textContent = device.label || ('Kamera ' + (idx + 1));
				select.appendChild(option);
			});
		} catch (e) {
			select.innerHTML = '<option value="">Kamera-Fehler</option>';
		}
	}
	enumerateGameCameras();

	// ─── Webcam start/stop ──────────────────────────────────────────────
	async function startGameWebcam() {
		if (webcamStream) return true;
		var deviceId = document.getElementById('game_camera_select').value;
		var constraints = { video: deviceId ? { deviceId: { exact: deviceId } } : true, audio: false };
		try {
			webcamStream = await navigator.mediaDevices.getUserMedia(constraints);
			video.srcObject = webcamStream;
			await new Promise(function(resolve) {
				video.onloadedmetadata = resolve;
				setTimeout(resolve, 3000);
			});
			await new Promise(function(resolve) {
				if (video.readyState >= 2) return resolve();
				video.oncanplay = resolve;
				setTimeout(resolve, 2000);
			});
			if (video.videoWidth > 0 && video.videoHeight > 0 && overlayCanvas) {
				overlayCanvas.width = video.videoWidth;
				overlayCanvas.height = video.videoHeight;
				overlayCanvas.style.width = video.clientWidth + 'px';
				overlayCanvas.style.height = video.clientHeight + 'px';
			}
			return true;
		} catch (e) {
			webcamStream = null;
			video.srcObject = null;
			appendOutput("FEHLER: Kamera konnte nicht gestartet werden - " + (e.message || "Unbekannt"));
			return false;
		}
	}

	function stopGameWebcam() {
		if (webcamStream) {
			try { webcamStream.getTracks().forEach(function(t) { t.stop(); }); } catch (e) {}
			webcamStream = null;
		}
		video.srcObject = null;
	}

	// ─── Model loading ──────────────────────────────────────────────────
	async function loadGameModel(modelUuid) {
		if (gameModel && currentModelUuid === modelUuid) return true;
		if (isLoadingModel) return false;
		isLoadingModel = true;
		setStatus('Modell wird geladen...');

		try {
			var resp = await fetch('labels.php?model_uuid=' + encodeURIComponent(modelUuid));
			if (resp.ok) gameLabels = await resp.json();
			else gameLabels = [];
		} catch (e) { gameLabels = []; }

		try {
			if (typeof tf === 'undefined') throw new Error("TensorFlow.js nicht geladen");
			await tf.ready();
			if (gameModel) try { gameModel.dispose(); } catch (e) {}
			gameModel = await tf.loadGraphModel(
				"get_model_file.php?&uuid=" + encodeURIComponent(modelUuid) + "&filename=model.json"
			);
			currentModelUuid = modelUuid;
			isLoadingModel = false;
			setStatus('Modell geladen ✓');
			return true;
		} catch (e) {
			gameModel = null;
			currentModelUuid = null;
			isLoadingModel = false;
			appendOutput("FEHLER: Modell konnte nicht geladen werden - " + (e.message || "Unbekannt"));
			setStatus('Modell-Fehler');
			return false;
		}
	}

	// ─── Detection logic ────────────────────────────────────────────────
	function getModelInputShape() {
		try {
			if (gameModel.inputs && gameModel.inputs[0] && gameModel.inputs[0].shape)
				return gameModel.inputs[0].shape.slice(1, 3);
		} catch (e) {}
		return [640, 640];
	}

	function getGameConfThreshold() {
		return parseFloat(document.getElementById('game_conf_slider').value) || 0.3;
	}

	function computeIoU(a, b) {
		var x1 = Math.max(a.xMin, b.xMin), y1 = Math.max(a.yMin, b.yMin);
		var x2 = Math.min(a.xMax, b.xMax), y2 = Math.min(a.yMax, b.yMax);
		var inter = Math.max(0, x2 - x1) * Math.max(0, y2 - y1);
		var union = (a.xMax - a.xMin) * (a.yMax - a.yMin) + (b.xMax - b.xMin) * (b.yMax - b.yMin) - inter;
		return union <= 0 ? 0 : inter / union;
	}

	function simpleNMS(detections, iouThresh) {
		if (!detections || detections.length === 0) return [];
		detections.sort(function(a, b) { return b.score - a.score; });
		var kept = [];
		for (var i = 0; i < detections.length; i++) {
			var dominated = false;
			for (var j = 0; j < kept.length; j++) {
				if (computeIoU(detections[i], kept[j]) > iouThresh) { dominated = true; break; }
			}
			if (!dominated) kept.push(detections[i]);
		}
		return kept;
	}

	async function runDetection() {
		if (!gameModel || !webcamStream || video.readyState < 2) return [];
		var shape = getModelInputShape();
		var confThreshold = getGameConfThreshold();
		var inputTensor = null, output = null;

		try {
			inputTensor = tf.tidy(function() {
				return tf.browser.fromPixels(video)
					.resizeBilinear([shape[0], shape[1]])
					.div(255)
					.expandDims();
			});
		} catch (e) {
			if (inputTensor) try { inputTensor.dispose(); } catch (x) {}
			return [];
		}

		try { output = await gameModel.execute(inputTensor); }
		catch (e) { if (inputTensor) try { inputTensor.dispose(); } catch (x) {} return []; }
		if (inputTensor) try { inputTensor.dispose(); } catch (x) {}

		var res;
		try {
			if (output instanceof tf.Tensor) {
				res = output.arraySync();
				output.dispose();
			} else if (Array.isArray(output)) {
				res = output[0].arraySync();
				output.forEach(function(t) { try { t.dispose(); } catch (x) {} });
			} else { res = output; }
		} catch (e) { return []; }

		try { return processOutput(res, shape[1], shape[0], confThreshold); }
		catch (e) { return []; }
	}

	function processOutput(res, modelWidth, modelHeight, confThreshold) {
		if (!res || !Array.isArray(res) || !Array.isArray(res[0]) || !Array.isArray(res[0][0])) return [];
		var rawTensor = null, predTensor = null;
		try {
			rawTensor = tf.tensor3d(res);
			var s = rawTensor.shape;
			if (s[1] > s[2]) {
				var transposed = rawTensor.transpose([0, 2, 1]);
				rawTensor.dispose();
				rawTensor = transposed;
			}
			var numClasses = rawTensor.shape[2] - 4;
			if (numClasses <= 0) { rawTensor.dispose(); return []; }

			predTensor = rawTensor.transpose([0, 2, 1]);
			var splits = tf.split(predTensor, [4, numClasses], 2);
			var rawBoxes = splits[0], scores = splits[1];
			var boxes = rawBoxes.squeeze(), scoresSqueezed = scores.squeeze();
			var boxesArr = boxes.arraySync(), scoresArr = scoresSqueezed.arraySync();

			[rawTensor, predTensor, rawBoxes, scores, boxes, scoresSqueezed].forEach(function(t) {
				if (t) try { t.dispose(); } catch (x) {}
			});

			var detections = [];
			for (var i = 0; i < boxesArr.length; i++) {
				var classScores = numClasses === 1 ? [scoresArr[i]] : scoresArr[i];
				var bestScore = 0, bestClass = -1;
				if (Array.isArray(classScores)) {
					for (var c = 0; c < classScores.length; c++) {
						if (classScores[c] > bestScore) { bestScore = classScores[c]; bestClass = c; }
					}
				} else { bestScore = classScores; bestClass = 0; }
				if (bestScore < confThreshold) continue;

				var cx = boxesArr[i][0], cy = boxesArr[i][1], w = boxesArr[i][2], h = boxesArr[i][3];
				var isPixel = cx > 2.0 || cy > 2.0;
				var xMin, yMin, xMax, yMax;
				if (isPixel) {
					xMin = (cx - w/2) / modelWidth; yMin = (cy - h/2) / modelHeight;
					xMax = (cx + w/2) / modelWidth; yMax = (cy + h/2) / modelHeight;
				} else {
					xMin = cx - w/2; yMin = cy - h/2; xMax = cx + w/2; yMax = cy + h/2;
				}
				xMin = Math.max(0, xMin); yMin = Math.max(0, yMin);
				xMax = Math.min(1, xMax); yMax = Math.min(1, yMax);

				var label = (gameLabels && gameLabels[bestClass]) ? gameLabels[bestClass] : ('class_' + bestClass);
				detections.push({ xMin: xMin, yMin: yMin, xMax: xMax, yMax: yMax, score: bestScore, label: label });
			}
			return simpleNMS(detections, 0.5);
		} catch (e) {
			if (rawTensor) try { rawTensor.dispose(); } catch (x) {}
			if (predTensor) try { predTensor.dispose(); } catch (x) {}
			return [];
		}
	}

	// ─── Draw detections on overlay ─────────────────────────────────────
	function drawGameDetections(detections) {
		if (!overlayCtx) return;
		var w = overlayCanvas.width, h = overlayCanvas.height;
		overlayCtx.clearRect(0, 0, w, h);
		if (!detections || detections.length === 0) return;

		var colors = ['#00ff88', '#ff6b9d', '#4fc3f7', '#ffb74d', '#ba68c8', '#e57373'];
		for (var i = 0; i < detections.length; i++) {
			var det = detections[i];
			var x = det.xMin * w, y = det.yMin * h;
			var bw = (det.xMax - det.xMin) * w;
			var bh = (det.yMax - det.yMin) * h;
			var color = colors[i % colors.length];

			overlayCtx.strokeStyle = color;
			overlayCtx.lineWidth = 3;
			overlayCtx.strokeRect(x, y, bw, bh);

			var text = det.label + ' ' + (det.score * 100).toFixed(0) + '%';
			overlayCtx.font = 'bold 14px "Nunito", sans-serif';
			var tw = overlayCtx.measureText(text).width;
			overlayCtx.fillStyle = color;
			overlayCtx.fillRect(x, y - 22, tw + 10, 22);
			overlayCtx.fillStyle = '#000';
			overlayCtx.fillText(text, x + 5, y - 6);
		}
	}

	// ─── DSL Parser & Interpreter ───────────────────────────────────────
	function parsePrintArgument(line) {
		if (!line.startsWith('print') && !line.startsWith('show_text')) return null;
		var keyword = line.startsWith('show_text') ? 'show_text' : 'print';
		var afterKeyword = line.substring(keyword.length);
		if (afterKeyword.startsWith('(')) {
			var depth = 0, inStr = false, strChar = '';
			for (var i = 0; i < afterKeyword.length; i++) {
				var ch = afterKeyword[i];
				if (!inStr && (ch === '"' || ch === "'")) { inStr = true; strChar = ch; }
				else if (inStr && ch === strChar) { inStr = false; }
				else if (!inStr && ch === '(') { depth++; }
				else if (!inStr && ch === ')') { depth--; if (depth === 0) return afterKeyword.substring(1, i).trim(); }
			}
			return afterKeyword.substring(1).trim();
		}
		if (afterKeyword.startsWith(' ') || afterKeyword.startsWith('\t')) {
			return afterKeyword.trim();
		}
		return null;
	}

	function tokenizeLine(line) {
		var commentIdx = line.indexOf('#');
		if (commentIdx !== -1) {
			var inStr = false, strChar = '';
			for (var i = 0; i < commentIdx; i++) {
				if (!inStr && (line[i] === '"' || line[i] === "'")) { inStr = true; strChar = line[i]; }
				else if (inStr && line[i] === strChar) { inStr = false; }
			}
			if (!inStr) line = line.substring(0, commentIdx);
		}
		return line.trim();
	}

	function parseScript(code) {
		var lines = code.split('\n');
		var parsed = [];
		for (var i = 0; i < lines.length; i++) {
			var trimmed = tokenizeLine(lines[i]);
			if (trimmed === '') continue;
			parsed.push({ lineNum: i + 1, text: trimmed });
		}
		return parsed;
	}

	function evaluateExpression(expr, context) {
		expr = expr.trim();
		if ((expr.startsWith('"') && expr.endsWith('"')) || (expr.startsWith("'") && expr.endsWith("'")))
			return expr.substring(1, expr.length - 1);
		if (!isNaN(expr) && expr !== '') return parseFloat(expr);
		if (expr.indexOf('+') !== -1) {
			var parts = splitOnPlus(expr);
			if (parts.length > 1) {
				var result = '';
				for (var i = 0; i < parts.length; i++) result += String(evaluateExpression(parts[i], context));
				return result;
			}
		}

		// Alle Detection-Builtins
		var builtins = {
		'leftmost_detection': context.leftmost_label,
			'rightmost_detection': context.rightmost_label,
			'topmost_detection': context.topmost_label,
			'bottommost_detection': context.bottommost_label,
			'largest_detection': context.largest_label,
			'smallest_detection': context.smallest_label,
			'highest_conf_detection': context.highest_conf_label,
			'leftmost_detection.probability': context.leftmost_prob,
			'rightmost_detection.probability': context.rightmost_prob,
			'topmost_detection.probability': context.topmost_prob,
			'bottommost_detection.probability': context.bottommost_prob,
			'largest_detection.probability': context.largest_prob,
			'smallest_detection.probability': context.smallest_prob,
			'highest_conf_detection.probability': context.highest_conf_prob,
			'detection_count': context.detection_count
		};

		if (builtins.hasOwnProperty(expr)) return builtins[expr];

		// User variables
		if (context.vars.hasOwnProperty(expr)) return context.vars[expr];

		return expr;
	}

	function splitOnPlus(expr) {
		var parts = [], current = '', inStr = false, strChar = '';
		for (var i = 0; i < expr.length; i++) {
			var ch = expr[i];
			if (!inStr && (ch === '"' || ch === "'")) { inStr = true; strChar = ch; current += ch; }
			else if (inStr && ch === strChar) { inStr = false; current += ch; }
			else if (!inStr && ch === '+') { parts.push(current); current = ''; }
			else { current += ch; }
		}
		parts.push(current);
		return parts;
	}

	function evaluateCondition(condStr, context) {
		condStr = condStr.trim();
		var andParts = splitLogical(condStr, ' and ');
		if (andParts.length > 1) {
			for (var i = 0; i < andParts.length; i++) { if (!evaluateCondition(andParts[i], context)) return false; }
			return true;
		}
		var orParts = splitLogical(condStr, ' or ');
		if (orParts.length > 1) {
			for (var i = 0; i < orParts.length; i++) {
				if (evaluateCondition(orParts[i], context)) return true;
			}
			return false;
		}

		// Handle 'not'
		if (condStr.startsWith('not ')) {
			return !evaluateCondition(condStr.substring(4), context);
		}

		// Comparison operators
		var operators = ['==', '!=', '>=', '<=', '>', '<'];
		for (var i = 0; i < operators.length; i++) {
			var op = operators[i];
			var opIdx = findOperatorIndex(condStr, op);
			if (opIdx !== -1) {
				var leftExpr = condStr.substring(0, opIdx).trim();
				var rightExpr = condStr.substring(opIdx + op.length).trim();
				var leftVal = evaluateExpression(leftExpr, context);
				var rightVal = evaluateExpression(rightExpr, context);

				switch (op) {
					case '==': return leftVal == rightVal;
					case '!=': return leftVal != rightVal;
					case '>=': return parseFloat(leftVal) >= parseFloat(rightVal);
					case '<=': return parseFloat(leftVal) <= parseFloat(rightVal);
					case '>':  return parseFloat(leftVal) > parseFloat(rightVal);
					case '<':  return parseFloat(leftVal) < parseFloat(rightVal);
				}
			}
		}

		// Truthy evaluation
		var val = evaluateExpression(condStr, context);
		return !!val && val !== "none" && val !== "0" && val !== "";
	}

	function findOperatorIndex(str, op) {
		var inStr = false, strChar = '';
		for (var i = 0; i <= str.length - op.length; i++) {
			var ch = str[i];
			if (!inStr && (ch === '"' || ch === "'")) { inStr = true; strChar = ch; }
			else if (inStr && ch === strChar) { inStr = false; }
			else if (!inStr && str.substring(i, i + op.length) === op) {
				if (op === '>' && i + 1 < str.length && str[i + 1] === '=') continue;
				if (op === '<' && i + 1 < str.length && str[i + 1] === '=') continue;
				if (op === '=' && i > 0 && (str[i - 1] === '!' || str[i - 1] === '>' || str[i - 1] === '<')) continue;
				return i;
			}
		}
		return -1;
	}

	function splitLogical(str, separator) {
		var parts = [];
		var current = '';
		var inStr = false, strChar = '';
		var sepLen = separator.length;

		for (var i = 0; i < str.length; i++) {
			var ch = str[i];
			if (!inStr && (ch === '"' || ch === "'")) { inStr = true; strChar = ch; current += ch; }
			else if (inStr && ch === strChar) { inStr = false; current += ch; }
			else if (!inStr && str.substring(i, i + sepLen) === separator) {
				parts.push(current);
				current = '';
				i += sepLen - 1;
			}
			else { current += ch; }
		}
		parts.push(current);
		return parts;
	}

	// ─── DSL Interpreter (block-based execution) ────────────────────────
	function interpretScript(parsedLines, context) {
		var output = [];
		var showTextCommands = [];

		function executeBlock(lines, startIdx) {
			var idx = startIdx;
			while (idx < lines.length) {
				var line = lines[idx].text;
				if (line === '') { idx++; continue; }

				// IF
				if (line.startsWith('if ')) {
					idx = executeIfBlock(lines, idx);
					continue;
				}

				// END
				if (line === 'end') { return idx + 1; }

				// ELIF / ELSE outside if-block
				if (line.startsWith('elif ') || line === 'else') { return idx; }

				// SHOW_TEXT (new command for overlay)
				if (line.startsWith('show_text ')) {
					var showArgs = parseShowTextArgs(line);
					if (showArgs) {
						var msg = evaluateExpression(showArgs.message, context);
						showTextCommands.push({ message: String(msg), style: showArgs.style || 'normal' });
					}
					idx++;
					continue;
				}

				// PRINT
				var printArg = parsePrintArgument(line);
				if (printArg !== null) {
					var printVal = evaluateExpression(printArg, context);
					output.push(String(printVal));
					idx++;
					continue;
				}

				// VARIABLE ASSIGNMENT
				var assignMatch = line.match(/^([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*(.+)$/);
				if (assignMatch) {
					context.vars[assignMatch[1]] = evaluateExpression(assignMatch[2].trim(), context);
					idx++;
					continue;
				}

				// Unknown — skip
				idx++;
			}
			return idx;
		}

		function executeIfBlock(lines, startIdx) {
			var idx = startIdx;
			var conditionMet = false;

			var ifLine = lines[idx].text;
			var ifCond = ifLine.substring(3).trim();
			idx++;

			if (evaluateCondition(ifCond, context)) {
				conditionMet = true;
				idx = executeBodyUntilElifElseEnd(lines, idx);
			} else {
				idx = skipBodyUntilElifElseEnd(lines, idx);
			}

			while (idx < lines.length) {
				var currentLine = lines[idx].text;

				if (currentLine.startsWith('elif ')) {
					if (!conditionMet) {
						var elifCond = currentLine.substring(5).trim();
						idx++;
						if (evaluateCondition(elifCond, context)) {
							conditionMet = true;
							idx = executeBodyUntilElifElseEnd(lines, idx);
						} else {
							idx = skipBodyUntilElifElseEnd(lines, idx);
						}
					} else {
						idx++;
						idx = skipBodyUntilElifElseEnd(lines, idx);
					}
				} else if (currentLine === 'else') {
					idx++;
					if (!conditionMet) {
						conditionMet = true;
						idx = executeBodyUntilElifElseEnd(lines, idx);
					} else {
						idx = skipBodyUntilElifElseEnd(lines, idx);
					}
				} else if (currentLine === 'end') {
					idx++;
					break;
				} else {
					break;
				}
			}
			return idx;
		}

		function executeBodyUntilElifElseEnd(lines, startIdx) {
			var idx = startIdx;
			while (idx < lines.length) {
				var line = lines[idx].text;

				if (line.startsWith('if ')) {
					idx = executeIfBlock(lines, idx);
					continue;
				}

				if (line === 'end') { return idx; }
				if (line.startsWith('elif ') || line === 'else') { return idx; }

				// SHOW_TEXT
				if (line.startsWith('show_text ')) {
					var showArgs = parseShowTextArgs(line);
					if (showArgs) {
						var msg = evaluateExpression(showArgs.message, context);
						showTextCommands.push({ message: String(msg), style: showArgs.style || 'normal' });
					}
					idx++;
					continue;
				}

				// PRINT
				var printArg = parsePrintArgument(line);
				if (printArg !== null) {
					var printVal = evaluateExpression(printArg, context);
					output.push(String(printVal));
				} else {
					var assignMatch = line.match(/^([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*(.+)$/);
					if (assignMatch) {
						context.vars[assignMatch[1]] = evaluateExpression(assignMatch[2].trim(), context);
					}
				}
				idx++;
			}
			return idx;
		}

		function skipBodyUntilElifElseEnd(lines, startIdx) {
			var idx = startIdx;
			var depth = 0;
			while (idx < lines.length) {
				var line = lines[idx].text;
				if (line.startsWith('if ')) { depth++; idx++; continue; }
				if (line === 'end') {
					if (depth === 0) return idx;
					depth--;
					idx++;
					continue;
				}
				if ((line.startsWith('elif ') || line === 'else') && depth === 0) {
					return idx;
				}
				idx++;
			}
			return idx;
		}

		executeBlock(parsedLines, 0);
		return { output: output, showTextCommands: showTextCommands };
	}

	// ─── Parse show_text arguments ──────────────────────────────────────
	function parseShowTextArgs(line) {
		// show_text "message" style
		// show_text variable style
		var afterCmd = line.substring('show_text '.length).trim();
		if (!afterCmd) return null;

		var message = '';
		var style = 'normal';

		// Check if starts with a quote
		if (afterCmd.startsWith('"') || afterCmd.startsWith("'")) {
			var quoteChar = afterCmd[0];
			var endQuote = afterCmd.indexOf(quoteChar, 1);
			if (endQuote !== -1) {
				message = afterCmd.substring(0, endQuote + 1);
				var rest = afterCmd.substring(endQuote + 1).trim();
				if (rest && ['normal', 'winner', 'loser', 'draw'].indexOf(rest) !== -1) {
					style = rest;
				}
			} else {
				message = afterCmd;
			}
		} else {
			// Not quoted — split on last space to find style
			var parts = afterCmd.split(' ');
			var lastPart = parts[parts.length - 1];
			if (['normal', 'winner', 'loser', 'draw'].indexOf(lastPart) !== -1 && parts.length > 1) {
				style = lastPart;
				message = parts.slice(0, -1).join(' ');
			} else {
				message = afterCmd;
			}
		}

		return { message: message, style: style };
	}

	// ─── Build context from detections ──────────────────────────────────
	function buildDSLContext(detections) {
		var context = {
		leftmost_label: "none",
			leftmost_prob: 0,
			rightmost_label: "none",
			rightmost_prob: 0,
			detection_count: 0,
			// NEU: Allgemeine Zugriffe
			topmost_label: "none",
			topmost_prob: 0,
			bottommost_label: "none",
			bottommost_prob: 0,
			largest_label: "none",
			largest_prob: 0,
			smallest_label: "none",
			smallest_prob: 0,
			highest_conf_label: "none",
			highest_conf_prob: 0,
			// Alle Detections als Array
			all_labels: [],
			vars: {}
	};

		if (!detections || detections.length === 0) return context;

		context.detection_count = detections.length;
		context.all_labels = detections.map(function(d) { return d.label; });

		var leftmost = detections[0];
		var rightmost = detections[0];
		var topmost = detections[0];
		var bottommost = detections[0];
		var largest = detections[0];
		var smallest = detections[0];
		var highestConf = detections[0];

		for (var i = 1; i < detections.length; i++) {
			var d = detections[i];
			if (d.xMin < leftmost.xMin) leftmost = d;
			if (d.xMax > rightmost.xMax) rightmost = d;
			if (d.yMin < topmost.yMin) topmost = d;
			if (d.yMax > bottommost.yMax) bottommost = d;

			var area = (d.xMax - d.xMin) * (d.yMax - d.yMin);
			var largestArea = (largest.xMax - largest.xMin) * (largest.yMax - largest.yMin);
			var smallestArea = (smallest.xMax - smallest.xMin) * (smallest.yMax - smallest.yMin);

			if (area > largestArea) largest = d;
			if (area < smallestArea) smallest = d;
			if (d.score > highestConf.score) highestConf = d;
		}

		context.leftmost_label = leftmost.label;
		context.leftmost_prob = leftmost.score;
		context.rightmost_label = rightmost.label;
		context.rightmost_prob = rightmost.score;
		context.topmost_label = topmost.label;
		context.topmost_prob = topmost.score;
		context.bottommost_label = bottommost.label;
		context.bottommost_prob = bottommost.score;
		context.largest_label = largest.label;
		context.largest_prob = largest.score;
		context.smallest_label = smallest.label;
		context.smallest_prob = smallest.score;
		context.highest_conf_label = highestConf.label;
		context.highest_conf_prob = highestConf.score;

		return context;
	}

	// ─── Game loop ──────────────────────────────────────────────────────
	async function gameStep() {
		if (!gameRunning) return;

		var modelUuid = document.getElementById('game_model_select').value;
		var detections = [];

		if (modelUuid !== 'none' && gameModel && webcamStream) {
			try {
				detections = await runDetection();
			} catch (e) {
				detections = [];
			}
		}

		drawGameDetections(detections);

		// Parse and run DSL script
		var code = editor.value;
		var parsed = parseScript(code);
		var context = buildDSLContext(detections);

		try {
			var results = interpretScript(parsed, context);

			// Handle print output
			if (results.output && results.output.length > 0) {
				for (var i = 0; i < results.output.length; i++) {
					appendOutput(results.output[i]);
				}
			}

			// Handle show_text overlay
			if (results.showTextCommands && results.showTextCommands.length > 0) {
				var lastCmd = results.showTextCommands[results.showTextCommands.length - 1];
				showTextOnVideo(lastCmd.message, lastCmd.style);
			} else {
				clearTextOverlay();
			}
		} catch (e) {
			appendOutput("FEHLER: " + (e.message || "Unbekannter Fehler"));
			clearTextOverlay();
		}

		// Update status
		setStatus('Läuft | Erkennungen: ' + detections.length +
			' | Links: ' + context.leftmost_label +
			' | Rechts: ' + context.rightmost_label);
	}

	// ─── Start / Stop game ──────────────────────────────────────────────
	async function startGame() {
		if (gameRunning) return;

		var modelUuid = document.getElementById('game_model_select').value;

		setStatus('Kamera wird gestartet...');
		var webcamOk = await startGameWebcam();
		if (!webcamOk) {
			setStatus('Kamera-Fehler');
			appendOutput("FEHLER: Kamera konnte nicht gestartet werden. Prüfe die Berechtigungen.");
			return;
		}

		if (modelUuid !== 'none') {
			setStatus('Modell wird geladen...');
			var modelOk = await loadGameModel(modelUuid);
			if (!modelOk) {
				setStatus('Modell-Fehler');
				appendOutput("WARNUNG: Modell konnte nicht geladen werden. Spiel läuft ohne Erkennung.");
			}
		} else {
			appendOutput("INFO: Kein Modell gewählt. Spiel läuft ohne Erkennung.");
		}

		gameRunning = true;
		var btn = document.getElementById('btn_run_game');
		btn.textContent = '⏸ Läuft...';
		btn.classList.add('running');

		var fps = parseInt(document.getElementById('game_fps').value) || 2;
		gameLoop = setInterval(gameStep, Math.round(1000 / fps));

		setStatus('Spiel läuft mit ' + fps + ' Auswertungen/Sek');
		appendOutput("=== 🎮 Spiel gestartet! ===");
	}

	function stopGame() {
		gameRunning = false;
		if (gameLoop) { clearInterval(gameLoop); gameLoop = null; }

		var btn = document.getElementById('btn_run_game');
		btn.textContent = '▶️ Spiel starten!';
		btn.classList.remove('running');

		stopGameWebcam();
		if (overlayCtx) overlayCtx.clearRect(0, 0, overlayCanvas.width, overlayCanvas.height);
		clearTextOverlay();
		setStatus('Gestoppt');
		appendOutput("=== ⏹ Spiel gestoppt ===");
	}

	// ─── Confidence slider ──────────────────────────────────────────────
	var gameConfSlider = document.getElementById('game_conf_slider');
	if (gameConfSlider) {
		gameConfSlider.addEventListener('input', function() {
			var display = document.getElementById('game_conf_value');
			if (display) display.textContent = parseFloat(this.value).toFixed(2);
		});
	}

	// ─── Bind buttons ───────────────────────────────────────────────────
	document.getElementById('btn_run_game').addEventListener('click', function() {
		if (gameRunning) stopGame();
		else startGame();
	});
	document.getElementById('btn_stop_game').addEventListener('click', stopGame);
	document.getElementById('btn_clear_output').addEventListener('click', clearOutput);

	// ─── FPS hot-swap ───────────────────────────────────────────────────
	var gameFpsInput = document.getElementById('game_fps');
	if (gameFpsInput) {
		var fpsDebounce = null;
		gameFpsInput.addEventListener('input', function() {
			if (!gameRunning || !gameLoop) return;
			clearTimeout(fpsDebounce);
			fpsDebounce = setTimeout(function() {
				clearInterval(gameLoop);
				var fps = Math.max(1, Math.min(10, parseInt(gameFpsInput.value) || 2));
				gameLoop = setInterval(gameStep, Math.round(1000 / fps));
				setStatus('Spiel läuft mit ' + fps + ' Auswertungen/Sek');
			}, 300);
		});
	}

	// ─── Cleanup on unload ──────────────────────────────────────────────
	window.addEventListener('beforeunload', function() {
		if (gameRunning) stopGame();
		if (gameModel) try { gameModel.dispose(); } catch (e) {}
	});
})();
