(function() {
	"use strict";

	// ─── State ──────────────────────────────────────────────────────────
	var gameRunning = false;
	var gameLoop = null;
	var webcamStream = null;
	var gameModel = null;
	var gameLabels = [];
	var currentModelUuid = null;
	var isLoadingModel = false;
	var currentDetections = [];

	var video = document.getElementById('game_video');
	var overlayCanvas = document.getElementById('game_overlay_canvas');
	var overlayCtx = overlayCanvas.getContext('2d');
	var editor = document.getElementById('dsl_editor');
	var outputDiv = document.getElementById('game_output');
	var statusDiv = document.getElementById('game_status');

	// ─── Editor: Tab support ────────────────────────────────────────────
	editor.addEventListener('keydown', function(e) {
		if (e.key === 'Tab') {
			e.preventDefault();
			var start = this.selectionStart;
			var end = this.selectionEnd;
			var value = this.value;

			if (e.shiftKey) {
				// Shift+Tab: remove leading tab/spaces on current line
				var lineStart = value.lastIndexOf('\n', start - 1) + 1;
				var lineText = value.substring(lineStart, end);
				if (lineText.startsWith('\t')) {
					this.value = value.substring(0, lineStart) + lineText.substring(1) + value.substring(end);
					this.selectionStart = Math.max(lineStart, start - 1);
					this.selectionEnd = Math.max(lineStart, end - 1);
				} else if (lineText.startsWith('    ')) {
					this.value = value.substring(0, lineStart) + lineText.substring(4) + value.substring(end);
					this.selectionStart = Math.max(lineStart, start - 4);
					this.selectionEnd = Math.max(lineStart, end - 4);
				}
			} else {
				// Tab: insert tab character
				this.value = value.substring(0, start) + '\t' + value.substring(end);
				this.selectionStart = this.selectionEnd = start + 1;
			}
		}

		// Ctrl+Enter to run
		if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
			e.preventDefault();
			if (!gameRunning) startGame();
		}
	});

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

	// ─── Camera enumeration ─────────────────────────────────────────────
	async function enumerateGameCameras() {
		var select = document.getElementById('game_camera_select');
		try {
			var tempStream = await navigator.mediaDevices.getUserMedia({ video: true });
			if (tempStream) tempStream.getTracks().forEach(function(t) { t.stop(); });
		} catch (e) {
			select.innerHTML = '<option value="">No camera access</option>';
			return;
		}
		try {
			var devices = await navigator.mediaDevices.enumerateDevices();
			var videoDevices = devices.filter(function(d) { return d.kind === 'videoinput'; });
			select.innerHTML = '';
			videoDevices.forEach(function(device, idx) {
				var option = document.createElement('option');
				option.value = device.deviceId;
				option.textContent = device.label || ('Camera ' + (idx + 1));
				select.appendChild(option);
			});
		} catch (e) {
			select.innerHTML = '<option value="">Camera error</option>';
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
			if (video.videoWidth > 0 && video.videoHeight > 0) {
				overlayCanvas.width = video.videoWidth;
				overlayCanvas.height = video.videoHeight;
				overlayCanvas.style.width = video.clientWidth + 'px';
				overlayCanvas.style.height = video.clientHeight + 'px';
			}
			return true;
		} catch (e) {
			webcamStream = null;
			video.srcObject = null;
			appendOutput("ERROR: Could not start webcam - " + (e.message || "Unknown error"));
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
		setStatus('Loading model...');

		try {
			var resp = await fetch('labels.php?model_uuid=' + encodeURIComponent(modelUuid));
			if (resp.ok) gameLabels = await resp.json();
			else gameLabels = [];
		} catch (e) { gameLabels = []; }

		try {
			if (typeof tf === 'undefined') throw new Error("TensorFlow.js not loaded");
			await tf.ready();
			if (gameModel) try { gameModel.dispose(); } catch (e) {}
			gameModel = await tf.loadGraphModel(
				"get_model_file.php?&uuid=" + encodeURIComponent(modelUuid) + "&filename=model.json"
			);
			currentModelUuid = modelUuid;
			isLoadingModel = false;
			setStatus('Model loaded');
			return true;
		} catch (e) {
			gameModel = null;
			currentModelUuid = null;
			isLoadingModel = false;
			appendOutput("ERROR: Failed to load model - " + (e.message || "Unknown"));
			setStatus('Model load failed');
			return false;
		}
	}

	// ─── Run prediction (reuses logic from webcam.js) ───────────────────
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
		var inputTensor = null;
		var output = null;

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

		try {
			output = await gameModel.execute(inputTensor);
		} catch (e) {
			if (inputTensor) try { inputTensor.dispose(); } catch (x) {}
			return [];
		}
		if (inputTensor) try { inputTensor.dispose(); } catch (x) {}

		var res;
		try {
			if (output instanceof tf.Tensor) {
				res = output.arraySync();
				output.dispose();
			} else if (Array.isArray(output)) {
				res = output[0].arraySync();
				output.forEach(function(t) { try { t.dispose(); } catch (x) {} });
			} else {
				res = output;
			}
		} catch (e) { return []; }

		try {
			return processOutput(res, shape[1], shape[0], confThreshold);
		} catch (e) { return []; }
	}

	function processOutput(res, modelWidth, modelHeight, confThreshold) {
		if (!res || !Array.isArray(res) || !Array.isArray(res[0]) || !Array.isArray(res[0][0])) return [];

		var rawTensor = null, predTensor = null;
		try {
			rawTensor = tf.tensor3d(res);
			var s = rawTensor.shape;
			if (s[1] <= s[2]) {
				// already correct orientation
			} else {
				var transposed = rawTensor.transpose([0, 2, 1]);
				rawTensor.dispose();
				rawTensor = transposed;
			}

			var numClasses = rawTensor.shape[2] - 4;
			if (numClasses <= 0) { rawTensor.dispose(); return []; }

			predTensor = rawTensor.transpose([0, 2, 1]);
			var splits = tf.split(predTensor, [4, numClasses], 2);
			var rawBoxes = splits[0];
			var scores = splits[1];
			var boxes = rawBoxes.squeeze();
			var scoresSqueezed = scores.squeeze();

			var boxesArr = boxes.arraySync();
			var scoresArr = scoresSqueezed.arraySync();

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
				} else {
					bestScore = classScores; bestClass = 0;
				}
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
		var w = overlayCanvas.width, h = overlayCanvas.height;
		overlayCtx.clearRect(0, 0, w, h);
		if (!detections || detections.length === 0) return;

		for (var i = 0; i < detections.length; i++) {
			var det = detections[i];
			var x = det.xMin * w, y = det.yMin * h;
			var bw = (det.xMax - det.xMin) * w;
			var bh = (det.yMax - det.yMin) * h;

			overlayCtx.strokeStyle = '#00ff88';
			overlayCtx.lineWidth = 2;
			overlayCtx.strokeRect(x, y, bw, bh);

			var text = det.label + ' ' + (det.score * 100).toFixed(0) + '%';
			overlayCtx.font = 'bold 13px monospace';
			var tw = overlayCtx.measureText(text).width;
			overlayCtx.fillStyle = 'rgba(0, 255, 100, 0.85)';
			overlayCtx.fillRect(x, y - 18, tw + 6, 18);
			overlayCtx.fillStyle = '#000';
			overlayCtx.fillText(text, x + 3, y - 4);
		}
	}

	// ─── DSL Parser & Interpreter ───────────────────────────────────────

	function parsePrintArgument(line) {
		// Check if line is a print statement in any form:
		// print "hello"
		// print("hello")
		// print("hello" + var)
		// print "hello" + var

		if (!line.startsWith('print')) return null;

		var afterPrint = line.substring(5); // everything after 'print'

		// Case 1: print("...") or print(expr)
		if (afterPrint.startsWith('(')) {
			// Find the matching closing paren
			var depth = 0;
			var inStr = false, strChar = '';
			for (var i = 0; i < afterPrint.length; i++) {
				var ch = afterPrint[i];
				if (!inStr && (ch === '"' || ch === "'")) { inStr = true; strChar = ch; }
				else if (inStr && ch === strChar) { inStr = false; }
				else if (!inStr && ch === '(') { depth++; }
				else if (!inStr && ch === ')') {
					depth--;
					if (depth === 0) {
						// Extract content between outer parens
						return afterPrint.substring(1, i).trim();
					}
				}
			}
			// If no matching paren found, treat the rest as the expression
			return afterPrint.substring(1).trim();
		}

		// Case 2: print "..." or print expr (space after print)
		if (afterPrint.startsWith(' ') || afterPrint.startsWith('\t')) {
			return afterPrint.trim();
		}

		return null;
	}

	function tokenizeLine(line) {
	    // Remove comments
	    var commentIdx = line.indexOf('#');
	    if (commentIdx !== -1) {
		// Make sure # is not inside a string
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

		// Handle braces: split a line that contains { or } into multiple logical lines
		// e.g., "if x == 1 {" becomes "if x == 1" and "{"
		// e.g., "} elif x == 2 {" becomes "}" and "elif x == 2" and "{"
		// e.g., "}" becomes "}"
		var expandedLines = expandBraces(trimmed);
		for (var j = 0; j < expandedLines.length; j++) {
		    var el = expandedLines[j].trim();
		    if (el === '') continue;
		    parsed.push({ lineNum: i + 1, text: el });
		}
	    }
	    return parsed;
	}

	function expandBraces(line) {
	    // We need to split on { and } but not inside strings
	    var results = [];
	    var current = '';
	    var inStr = false, strChar = '';

	    for (var i = 0; i < line.length; i++) {
		var ch = line[i];
		if (!inStr && (ch === '"' || ch === "'")) {
		    inStr = true; strChar = ch; current += ch;
		} else if (inStr && ch === strChar) {
		    inStr = false; current += ch;
		} else if (!inStr && ch === '{') {
		    // Everything before '{' is one segment
		    var before = current.trim();
		    if (before !== '') results.push(before);
		    current = '';
		    // '{' is treated as a no-op (block opener), we just skip it
		    // It signals that the block starts on the next line/statement
		} else if (!inStr && ch === '}') {
		    // Everything before '}' is one segment
		    var before = current.trim();
		    if (before !== '') results.push(before);
		    current = '';
		    // '}' is equivalent to 'end'
		    results.push('end');
		} else {
		    current += ch;
		}
	    }

	    var remaining = current.trim();
	    if (remaining !== '') results.push(remaining);

	    return results;
	}

	function evaluateExpression(expr, context) {
		expr = expr.trim();

		// String literal
		if ((expr.startsWith('"') && expr.endsWith('"')) || (expr.startsWith("'") && expr.endsWith("'"))) {
			return expr.substring(1, expr.length - 1);
		}

		// Number literal
		if (!isNaN(expr) && expr !== '') {
			return parseFloat(expr);
		}

		// String concatenation with +
		if (expr.indexOf('+') !== -1) {
			var parts = splitOnPlus(expr);
			if (parts.length > 1) {
				var result = '';
				for (var i = 0; i < parts.length; i++) {
					var val = evaluateExpression(parts[i], context);
					result += String(val);
				}
				return result;
			}
		}

		// Built-in variables
		if (expr === 'leftmost_detection') return context.leftmost_label;
		if (expr === 'rightmost_detection') return context.rightmost_label;
		if (expr === 'leftmost_detection.probability') return context.leftmost_prob;
		if (expr === 'rightmost_detection.probability') return context.rightmost_prob;
		if (expr === 'detection_count') return context.detection_count;

		// User variables
		if (context.vars.hasOwnProperty(expr)) return context.vars[expr];

		// Unknown
		return expr;
	}

	function splitOnPlus(expr) {
		var parts = [];
		var current = '';
		var inStr = false, strChar = '';
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

		// Handle 'and' / 'or'
		var andParts = splitLogical(condStr, ' and ');
		if (andParts.length > 1) {
			for (var i = 0; i < andParts.length; i++) {
				if (!evaluateCondition(andParts[i], context)) return false;
			}
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

		// Truthy evaluation (bare variable or expression)
		var val = evaluateExpression(condStr, context);
		return !!val && val !== "none" && val !== "0" && val !== "";
	}

	function findOperatorIndex(str, op) {
		// Find operator not inside a string literal
		var inStr = false, strChar = '';
		for (var i = 0; i <= str.length - op.length; i++) {
			var ch = str[i];
			if (!inStr && (ch === '"' || ch === "'")) { inStr = true; strChar = ch; }
			else if (inStr && ch === strChar) { inStr = false; }
			else if (!inStr && str.substring(i, i + op.length) === op) {
				// Make sure we don't match >= when looking for >
				if (op === '>' && i + 1 < str.length && str[i + 1] === '=') continue;
				if (op === '<' && i + 1 < str.length && str[i + 1] === '=') continue;
				// Make sure we don't match != or == partially
				if (op === '=' && i > 0 && (str[i - 1] === '!' || str[i - 1] === '>' || str[i - 1] === '<')) continue;
				return i;
			}
		}
		return -1;
	}

	function splitLogical(str, separator) {
		// Split on logical operator, respecting string literals
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
		var i = 0;

		function executeBlock(lines, startIdx) {
			var idx = startIdx;
			while (idx < lines.length) {
				var line = lines[idx].text;

				// Skip empty/comment-only lines (already filtered, but just in case)
				if (line === '') { idx++; continue; }

				// ─── IF / ELIF / ELSE / END ─────────────────────────
				if (line.startsWith('if ')) {
					idx = executeIfBlock(lines, idx);
					continue;
				}

				// END closes current block (return to caller)
				if (line === 'end') {
					return idx + 1;
				}

				// ELIF / ELSE encountered outside if-block means we're done with this block
				if (line.startsWith('elif ') || line === 'else') {
					return idx;
				}

				// ─── PRINT (supports both print "x" and print("x")) ─
				var printArg = parsePrintArgument(line);
				if (printArg !== null) {
					var printVal = evaluateExpression(printArg, context);
					output.push(String(printVal));
					idx++;
					continue;
				}

				// ─── VARIABLE ASSIGNMENT ────────────────────────────
				var assignMatch = line.match(/^([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*(.+)$/);
				if (assignMatch) {
					var varName = assignMatch[1];
					var varExpr = assignMatch[2].trim();
					context.vars[varName] = evaluateExpression(varExpr, context);
					idx++;
					continue;
				}

				// Unknown line — skip silently (forgiving parser)
				idx++;
			}
			return idx;
		}

		function executeIfBlock(lines, startIdx) {
			var idx = startIdx;
			var conditionMet = false;

			// Parse the 'if' condition
			var ifLine = lines[idx].text;
			var ifCond = ifLine.substring(3).trim();
			idx++;

			if (evaluateCondition(ifCond, context)) {
				conditionMet = true;
				idx = executeBodyUntilElifElseEnd(lines, idx);
			} else {
				idx = skipBodyUntilElifElseEnd(lines, idx);
			}

			// Process elif / else chains
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
					// No more elif/else/end — implicit end
					break;
				}
			}

			return idx;
		}

		function executeBodyUntilElifElseEnd(lines, startIdx) {
			var idx = startIdx;
			var depth = 0;
			while (idx < lines.length) {
				var line = lines[idx].text;

				if (line.startsWith('if ')) {
					// Nested if — execute it
					idx = executeIfBlock(lines, idx);
					continue;
				}

				if (line === 'end') {
					if (depth === 0) return idx; // don't consume 'end', let caller handle
					depth--;
					idx++;
					continue;
				}

				if (line.startsWith('elif ') || line === 'else') {
					if (depth === 0) return idx; // don't consume, let caller handle
					idx++;
					continue;
				}

				// ─── PRINT (supports both print "x" and print("x")) ─
				var printArg = parsePrintArgument(line);
				if (printArg !== null) {
					var printVal = evaluateExpression(printArg, context);
					output.push(String(printVal));
				} else {
					// ─── VARIABLE ASSIGNMENT ────────────────────────
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
		return output;
	}

	// ─── Build context from detections ──────────────────────────────────
	function buildDSLContext(detections) {
		var context = {
			leftmost_label: "none",
			leftmost_prob: 0,
			rightmost_label: "none",
			rightmost_prob: 0,
			detection_count: 0,
			vars: {}
		};

		if (!detections || detections.length === 0) return context;

		context.detection_count = detections.length;

		// Find leftmost (smallest xMin)
		var leftmost = detections[0];
		var rightmost = detections[0];
		for (var i = 1; i < detections.length; i++) {
			if (detections[i].xMin < leftmost.xMin) leftmost = detections[i];
			if (detections[i].xMax > rightmost.xMax) rightmost = detections[i];
		}

		context.leftmost_label = leftmost.label;
		context.leftmost_prob = leftmost.score;
		context.rightmost_label = rightmost.label;
		context.rightmost_prob = rightmost.score;

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

		currentDetections = detections;
		drawGameDetections(detections);

		// Parse and run DSL script
		var code = editor.value;
		var parsed = parseScript(code);
		var context = buildDSLContext(detections);

		try {
			var results = interpretScript(parsed, context);
			if (results.length > 0) {
				for (var i = 0; i < results.length; i++) {
					appendOutput(results[i]);
				}
			}
		} catch (e) {
			appendOutput("SCRIPT ERROR: " + (e.message || "Unknown error"));
		}

		// Update status
		setStatus('Running | Detections: ' + detections.length +
			' | Left: ' + context.leftmost_label +
			' | Right: ' + context.rightmost_label);
	}

	// ─── Start / Stop game ──────────────────────────────────────────────
	async function startGame() {
		if (gameRunning) return;

		var modelUuid = document.getElementById('game_model_select').value;

		// Always start webcam
		setStatus('Starting webcam...');
		var webcamOk = await startGameWebcam();
		if (!webcamOk) {
			setStatus('Failed to start webcam');
			appendOutput("ERROR: Could not start webcam. Check camera permissions.");
			return;
		}

		// Only load model if one is selected
		if (modelUuid !== 'none') {
			setStatus('Loading model...');
			var modelOk = await loadGameModel(modelUuid);
			if (!modelOk) {
				setStatus('Failed to load model');
				appendOutput("ERROR: Could not load model. Game will run without detections.");
				// Don't return — let the game run without detections so the webcam still shows
			}
		} else {
			appendOutput("INFO: No model selected. Running without detections.");
		}

		gameRunning = true;
		var btn = document.getElementById('btn_run_game');
		btn.textContent = '⏸ Running...';
		btn.classList.add('running');

		var fps = parseInt(document.getElementById('game_fps').value) || 2;
		gameLoop = setInterval(gameStep, Math.round(1000 / fps));

		setStatus('Game running at ' + fps + ' eval/sec');
		appendOutput("=== Game Started ===");
	}

	function stopGame() {
		gameRunning = false;
		if (gameLoop) { clearInterval(gameLoop); gameLoop = null; }

		var btn = document.getElementById('btn_run_game');
		btn.textContent = '▶ Run Game';
		btn.classList.remove('running');

		stopGameWebcam();
		overlayCtx.clearRect(0, 0, overlayCanvas.width, overlayCanvas.height);
		setStatus('Stopped');
		appendOutput("=== Game Stopped ===");
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
				setStatus('Game running at ' + fps + ' eval/sec');
			}, 300);
		});
	}

	// ─── Cleanup on unload ──────────────────────────────────────────────
	window.addEventListener('beforeunload', function() {
		if (gameRunning) stopGame();
		if (gameModel) try { gameModel.dispose(); } catch (e) {}
	});

})();
