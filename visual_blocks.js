// ═══════════════════════════════════════════════════════════════════════════
// VISUAL BLOCK EDITOR — Drag & drop blocks, auto-sync to DSL
// Colorful, purely visual, select-box conditions, indentation, snap logic
// ═══════════════════════════════════════════════════════════════════════════

(function() {
	"use strict";

	var workspace = document.getElementById('block_workspace');
	var palette = document.getElementById('block_palette');
	var placeholder = document.getElementById('workspace_placeholder');
	var trashZone = document.getElementById('trash_zone');
	var dslEditor = document.getElementById('dsl_editor');

	var draggedBlock = null;
	var draggedFromWorkspace = false;
	var dragGhost = null;
	var snapIndicator = null;

	// ─── Available variables/values for dropdowns ────────────────────────
	var sensorVars = ['links', 'rechts', 'oben', 'unten', 'anzahl', 'größtes', 'kleinstes', 'bestes'];
	var operators = [
		{ value: '==', label: 'ist gleich' },
		{ value: '!=', label: 'ist nicht' },
		{ value: '>=', label: 'ist mindestens' },
		{ value: '<=', label: 'ist höchstens' },
		{ value: '>', label: 'ist mehr als' },
		{ value: '<', label: 'ist weniger als' }
	];

	// Dynamic labels from model (updated externally)
	var modelLabels = [];

	function getCompareValues() {
		var values = [
			{ value: '"none"', label: '❌ nichts' },
			{ value: '0', label: '0' },
			{ value: '1', label: '1' },
			{ value: '2', label: '2' },
			{ value: '3', label: '3' }
		];
		for (var i = 0; i < modelLabels.length; i++) {
			values.push({ value: '"' + modelLabels[i] + '"', label: '🏷️ ' + modelLabels[i] });
		}
		// Also add sensor vars as compare targets
		for (var i = 0; i < sensorVars.length; i++) {
			values.push({ value: sensorVars[i], label: '📦 ' + sensorVars[i] });
		}
		return values;
	}

	// Expose label update function
	window.updateBlockEditorLabels = function(labels) {
		modelLabels = labels || [];
		// Refresh all condition dropdowns in workspace
		var blocks = workspace.querySelectorAll('.workspace-block');
		for (var i = 0; i < blocks.length; i++) {
			var type = blocks[i].getAttribute('data-block-type');
			if (type === 'if' || type === 'elif') {
				refreshConditionSelects(blocks[i]);
			}
		}
	};

	function refreshConditionSelects(block) {
		var selects = block.querySelectorAll('select.cond-value');
		var values = getCompareValues();
		for (var i = 0; i < selects.length; i++) {
			var currentVal = selects[i].value;
			selects[i].innerHTML = '';
			for (var j = 0; j < values.length; j++) {
				var opt = document.createElement('option');
				opt.value = values[j].value;
				opt.textContent = values[j].label;
				if (values[j].value === currentVal) opt.selected = true;
				selects[i].appendChild(opt);
			}
		}
	}

	// ─── Indentation calculation ────────────────────────────────────────
	function recalcIndentation() {
		var blocks = workspace.querySelectorAll('.workspace-block');
		var indent = 0;
		for (var i = 0; i < blocks.length; i++) {
			var type = blocks[i].getAttribute('data-block-type');

			// 'end', 'elif', 'else' reduce indent before rendering
			if (type === 'end' || type === 'elif' || type === 'else') {
				indent = Math.max(0, indent - 1);
			}

			blocks[i].setAttribute('data-indent', indent);
			blocks[i].style.marginLeft = (indent * 28) + 'px';

			// 'if', 'elif', 'else' increase indent after rendering
			if (type === 'if' || type === 'elif' || type === 'else') {
				indent++;
			}
		}
	}

	// ─── Snap validation: check if block can be placed at position ──────
	function canSnap(blockType, targetBlock, position) {
		if (!targetBlock) return true; // dropping into empty workspace

		var targetType = targetBlock.getAttribute('data-block-type');
		var blocks = Array.from(workspace.querySelectorAll('.workspace-block'));
		var targetIdx = blocks.indexOf(targetBlock);

		// 'elif' and 'else' can only snap after an 'if' body or another 'elif'
		if (blockType === 'elif' || blockType === 'else') {
			// Find the block that would be above this one
			var aboveIdx = position === 'above' ? targetIdx - 1 : targetIdx;
			if (aboveIdx < 0) return false;

			// Walk backwards to find if we're inside an if-chain
			var depth = 0;
			for (var i = aboveIdx; i >= 0; i--) {
				var t = blocks[i].getAttribute('data-block-type');
				if (t === 'end') depth++;
				if (t === 'if' && depth === 0) return true;
				if (t === 'elif' && depth === 0) return true;
				if (t === 'else' && depth === 0) return (blockType === 'end');
				if (t === 'if') depth--;
			}
			return false;
		}

		// 'end' should only snap where there's an open if/elif/else
		if (blockType === 'end') {
			var openBlocks = 0;
			var checkIdx = position === 'above' ? targetIdx : targetIdx + 1;
			for (var i = 0; i < checkIdx && i < blocks.length; i++) {
				var t = blocks[i].getAttribute('data-block-type');
				if (t === 'if') openBlocks++;
				if (t === 'end') openBlocks--;
			}
			return openBlocks > 0;
		}

		return true;
	}

	// ─── Sync blocks to DSL code ────────────────────────────────────────
	function syncBlocksToDSL() {
		recalcIndentation();
		var blocks = workspace.querySelectorAll('.workspace-block');
		var lines = [];
		for (var i = 0; i < blocks.length; i++) {
			var code = getBlockCode(blocks[i]);
			if (code !== null) lines.push(code);
		}
		dslEditor.value = lines.join('\n');

		// Show/hide placeholder
		if (placeholder) {
			placeholder.style.display = blocks.length === 0 ? 'block' : 'none';
		}
	}

	function getBlockCode(block) {
		var type = block.getAttribute('data-block-type');
		var selects = block.querySelectorAll('select');
		var inputs = block.querySelectorAll('input');

		switch (type) {
			case 'get_left':
				return 'links = leftmost_detection';
			case 'get_right':
				return 'rechts = rightmost_detection';
			case 'get_count':
				return 'anzahl = detection_count';
			case 'get_top':
				return 'oben = topmost_detection';
			case 'get_bottom':
				return 'unten = bottommost_detection';
			case 'get_largest':
				return 'groesstes = largest_detection';
			case 'get_smallest':
				return 'kleinstes = smallest_detection';
			case 'get_best':
				return 'bestes = highest_conf_detection';
			case 'if':
			case 'elif':
				var keyword = type === 'if' ? 'if' : 'elif';
				var condLeft = getSelectValue(selects, 0) || 'links';
				var condOp = getSelectValue(selects, 1) || '==';
				var condRight = getSelectValue(selects, 2) || '"none"';
				return keyword + ' ' + condLeft + ' ' + condOp + ' ' + condRight;
			case 'else':
				return 'else';
			case 'end':
				return 'end';
			case 'print':
				return 'print ' + (getInputOrSelectValue(block, 0) || '"Hallo!"');
			case 'show_text':
				var msg = getInputOrSelectValue(block, 0) || '"Hallo!"';
				var style = getSelectByClass(block, 'style-select') || 'normal';
				return 'show_text ' + msg + ' ' + style;
			case 'set_var':
				var vname = getSelectByClass(block, 'varname-select') || 'x';
				var vval = getInputOrSelectValue(block, 0) || '0';
				return vname + ' = ' + vval;
			default:
				return '# unknown block: ' + type;
		}
	}

	function getSelectValue(selects, index) {
		if (selects && selects.length > index) return selects[index].value;
		return null;
	}

	function getSelectByClass(block, className) {
		var el = block.querySelector('select.' + className);
		return el ? el.value : null;
	}

	function getInputOrSelectValue(block, index) {
		var inputs = block.querySelectorAll('input.block-input');
		if (inputs && inputs.length > index) return inputs[index].value;
		return null;
	}

	// ─── Build select element helper ────────────────────────────────────
	function buildSelect(options, selectedValue, className) {
		var select = document.createElement('select');
		select.className = 'block-select ' + (className || '');
		for (var i = 0; i < options.length; i++) {
			var opt = document.createElement('option');
			opt.value = options[i].value;
			opt.textContent = options[i].label;
			if (options[i].value === selectedValue) opt.selected = true;
			select.appendChild(opt);
		}
		return select;
	}

	// ─── Create workspace block from type ───────────────────────────────
	function createWorkspaceBlock(type, data) {
		var block = document.createElement('div');
		block.className = 'workspace-block';
		block.setAttribute('data-block-type', type);
		block.setAttribute('draggable', 'true');

		var cat = getCategoryClass(type);
		if (cat) block.classList.add(cat);

		buildBlockDOM(block, type, data);

		// Delete button
		var delBtn = document.createElement('button');
		delBtn.className = 'block-delete';
		delBtn.textContent = '✕';
		delBtn.title = 'Block löschen';
		delBtn.addEventListener('click', function(e) {
			e.stopPropagation();
			block.remove();
			syncBlocksToDSL();
		});
		block.appendChild(delBtn);

		// Input/select change listeners
		attachChangeListeners(block);

		// Drag events for reordering
		block.addEventListener('dragstart', function(e) {
			draggedBlock = this;
			draggedFromWorkspace = true;
			this.classList.add('dragging');
			if (trashZone) trashZone.classList.add('visible');
			e.dataTransfer.effectAllowed = 'move';
			e.dataTransfer.setData('text/plain', '');
		});

		block.addEventListener('dragend', function() {
			this.classList.remove('dragging');
			if (trashZone) trashZone.classList.remove('visible');
			clearDropIndicators();
			draggedBlock = null;
			draggedFromWorkspace = false;
		});

		block.addEventListener('dragover', function(e) {
			e.preventDefault();
			if (!draggedBlock) return;

			var dragType = draggedBlock.getAttribute('data-block-type');
			var rect = this.getBoundingClientRect();
			var midY = rect.top + rect.height / 2;
			var position = e.clientY < midY ? 'above' : 'below';

			clearDropIndicators();

			// Only show indicator if snap is valid
			if (canSnap(dragType, this, position)) {
				if (position === 'above') {
					this.classList.add('drop-above');
				} else {
					this.classList.add('drop-below');
				}
			} else {
				this.classList.add('drop-invalid');
			}
		});

		block.addEventListener('dragleave', function() {
			this.classList.remove('drop-above', 'drop-below', 'drop-invalid');
		});

		block.addEventListener('drop', function(e) {
			e.preventDefault();
			e.stopPropagation();
			if (!draggedBlock || draggedBlock === this) {
				clearDropIndicators();
				return;
			}

			var dragType = draggedBlock.getAttribute('data-block-type');
			var rect = this.getBoundingClientRect();
			var midY = rect.top + rect.height / 2;
			var position = e.clientY < midY ? 'above' : 'below';

			// Validate snap
			if (!canSnap(dragType, this, position)) {
				clearDropIndicators();
				shakeBlock(this);
				return;
			}

			if (draggedFromWorkspace) {
				if (position === 'above') {
					workspace.insertBefore(draggedBlock, this);
				} else {
					workspace.insertBefore(draggedBlock, this.nextSibling);
				}
			} else {
				var newBlock = createWorkspaceBlock(
					draggedBlock.getAttribute('data-block-type'),
					{ label: draggedBlock.getAttribute('data-label') }
				);
				if (position === 'above') {
					workspace.insertBefore(newBlock, this);
				} else {
					workspace.insertBefore(newBlock, this.nextSibling);
				}
			}

			clearDropIndicators();
			syncBlocksToDSL();
		});

		return block;
	}

	function shakeBlock(block) {
		block.classList.add('shake');
		setTimeout(function() { block.classList.remove('shake'); }, 400);
	}

	function attachChangeListeners(block) {
		var elements = block.querySelectorAll('input, select');
		for (var i = 0; i < elements.length; i++) {
			elements[i].addEventListener('input', syncBlocksToDSL);
			elements[i].addEventListener('change', syncBlocksToDSL);
		}
	}

	function getCategoryClass(type) {
		var map = {
			'get_left': 'cat-sensing', 'get_right': 'cat-sensing',
			'get_count': 'cat-sensing', 'get_top': 'cat-sensing',
			'get_bottom': 'cat-sensing', 'get_largest': 'cat-sensing',
			'get_smallest': 'cat-sensing', 'get_best': 'cat-sensing',
			'if': 'cat-control', 'elif': 'cat-control',
			'else': 'cat-control', 'end': 'cat-control',
			'print': 'cat-output', 'show_text': 'cat-display',
			'set_var': 'cat-variables', 'label_value': 'cat-labels'
		};
		return map[type] || '';
	}

	// ─── Build block DOM (purely visual, no text inputs for conditions) ──
	function buildBlockDOM(block, type, data) {
		var content = document.createElement('div');
		content.className = 'block-content';

		switch (type) {
			case 'get_left':
				content.innerHTML = '<span class="block-icon">🎯</span><span class="block-label"><strong>links</strong> = was links ist</span>';
				break;
			case 'get_right':
				content.innerHTML = '<span class="block-icon">🎯</span><span class="block-label"><strong>rechts</strong> = was rechts ist</span>';
				break;
			case 'get_count':
				content.innerHTML = '<span class="block-icon">🔢</span><span class="block-label"><strong>anzahl</strong> = wie viele?</span>';
				break;
			case 'get_top':
				content.innerHTML = '<span class="block-icon">🎯</span><span class="block-label"><strong>oben</strong> = was oben ist</span>';
				break;
			case 'get_bottom':
				content.innerHTML = '<span class="block-icon">🎯</span><span class="block-label"><strong>unten</strong> = was unten ist</span>';
				break;
			case 'get_largest':
				content.innerHTML = '<span class="block-icon">🎯</span><span class="block-label"><strong>größtes</strong> = größte Erkennung</span>';
				break;
			case 'get_smallest':
				content.innerHTML = '<span class="block-icon">🎯</span><span class="block-label"><strong>kleinstes</strong> = kleinste Erkennung</span>';
				break;
			case 'get_best':
				content.innerHTML = '<span class="block-icon">🎯</span><span class="block-label"><strong>bestes</strong> = sicherste Erkennung</span>';
				break;

			case 'if':
			case 'elif':
				var keyword = type === 'if' ? 'wenn' : 'sonst wenn';
				var icon = type === 'if' ? '🔶' : '🔷';
				var condData = (data && data.condition) || {};

				content.innerHTML = '<span class="block-icon">' + icon + '</span><span class="block-keyword">' + keyword + '</span>';

				// Left operand select
				var leftOpts = sensorVars.map(function(v) { return { value: v, label: '📦 ' + v }; });
				leftOpts.push({ value: 'detection_count', label: '🔢 anzahl' });
				var leftSelect = buildSelect(leftOpts, condData.left || 'links', 'cond-left');
				content.appendChild(leftSelect);

				// Operator select
				var opSelect = buildSelect(operators, condData.op || '==', 'cond-op');
				content.appendChild(opSelect);

				// Right operand select
				var rightSelect = buildSelect(getCompareValues(), condData.right || '"none"', 'cond-value');
				content.appendChild(rightSelect);

				var thenSpan = document.createElement('span');
				thenSpan.className = 'block-keyword';
				thenSpan.textContent = 'dann';
				content.appendChild(thenSpan);
				break;

			case 'else':
				content.innerHTML = '<span class="block-icon">⬜</span><span class="block-keyword">sonst</span>';
				break;

			case 'end':
				content.innerHTML = '<span class="block-icon">🔚</span><span class="block-keyword">ende</span>';
				break;

			case 'print':
				content.innerHTML = '<span class="block-icon">💬</span><span class="block-keyword">sag</span>';
				var printInput = document.createElement('input');
				printInput.type = 'text';
				printInput.className = 'block-input';
				printInput.value = (data && data.inputs && data.inputs[0]) || '"Hallo!"';
				printInput.placeholder = 'Text oder Variable';
				printInput.style.width = '160px';
				content.appendChild(printInput);
				break;

			case 'show_text':
				content.innerHTML = '<span class="block-icon">📺</span><span class="block-keyword">zeige</span>';
				var showInput = document.createElement('input');
				showInput.type = 'text';
				showInput.className = 'block-input';
				showInput.value = (data && data.inputs && data.inputs[0]) || '"Hallo!"';
				showInput.placeholder = 'Text oder Variable';
				showInput.style.width = '130px';
				content.appendChild(showInput);

				var styleOpts = [
					{ value: 'normal', label: '😐 Normal' },
					{ value: 'winner', label: '🎉 Gewinner' },
					{ value: 'loser', label: '😢 Verlierer' },
					{ value: 'draw', label: '🤝 Gleich' }
				];
				var styleSelect = buildSelect(styleOpts, (data && data.inputs && data.inputs[1]) || 'normal', 'style-select');
				content.appendChild(styleSelect);
				break;

			case 'set_var':
				content.innerHTML = '<span class="block-icon">📦</span><span class="block-keyword">setze</span>';
				var varOpts = [
					{ value: 'x', label: 'x' },
					{ value: 'y', label: 'y' },
					{ value: 'punkte', label: 'punkte' },
					{ value: 'runde', label: 'runde' },
					{ value: 'ergebnis', label: 'ergebnis' }
				];
				var varSelect = buildSelect(varOpts, (data && data.inputs && data.inputs[0]) || 'x', 'varname-select');
				content.appendChild(varSelect);

				var eqSpan = document.createElement('span');
				eqSpan.className = 'block-keyword';
				eqSpan.textContent = '=';
				content.appendChild(eqSpan);

				var valInput = document.createElement('input');
				valInput.type = 'text';
				valInput.className = 'block-input';
				valInput.value = (data && data.inputs && data.inputs[1]) || '0';
				valInput.placeholder = 'Wert';
				valInput.style.width = '80px';
				content.appendChild(valInput);
				break;

			case 'label_value':
				var label = (data && data.label) || '???';
				content.innerHTML = '<span class="block-icon">🏷️</span><span class="block-label"><strong>"' + label + '"</strong></span>';
				break;

			default:
				content.innerHTML = '<span class="block-icon">❓</span><span class="block-label">Unbekannt</span>';
		}

		block.appendChild(content);
	}

	// ─── Workspace drop zone ────────────────────────────────────────────
	workspace.addEventListener('dragover', function(e) {
		e.preventDefault();
		// Only highlight if valid
		if (draggedBlock) {
			var dragType = draggedBlock.getAttribute('data-block-type');
			var blocks = workspace.querySelectorAll('.workspace-block');
			if (blocks.length === 0 || canSnap(dragType, null, 'below')) {
				workspace.classList.add('drag-over');
			}
		}
	});

	workspace.addEventListener('dragleave', function(e) {
		if (!workspace.contains(e.relatedTarget)) {
			workspace.classList.remove('drag-over');
		}
	});

	workspace.addEventListener('drop', function(e) {
		e.preventDefault();
		workspace.classList.remove('drag-over');

		if (!draggedBlock) return;

		var dragType = draggedBlock.getAttribute('data-block-type');

		if (draggedFromWorkspace) {
			// Already handled by block-level drop, but if dropped on empty space at bottom
			workspace.appendChild(draggedBlock);
			syncBlocksToDSL();
			return;
		}

		// Validate: elif/else/end can't be first block
		var blocks = workspace.querySelectorAll('.workspace-block');
		if (blocks.length === 0 && (dragType === 'elif' || dragType === 'else' || dragType === 'end')) {
			return; // Can't start with these
		}

		// New block from palette
		var newBlock = createWorkspaceBlock(dragType, {
			label: draggedBlock.getAttribute('data-label')
		});
		workspace.appendChild(newBlock);
		syncBlocksToDSL();
	});

	// ─── Palette drag events ────────────────────────────────────────────
	function initPaletteDrag() {
		var paletteBlocks = palette.querySelectorAll('.palette-block');
		for (var i = 0; i < paletteBlocks.length; i++) {
			attachPaletteDrag(paletteBlocks[i]);
		}
	}

	function attachPaletteDrag(block) {
		block.addEventListener('dragstart', function(e) {
			draggedBlock = this;
			draggedFromWorkspace = false;
			if (trashZone) trashZone.classList.remove('visible');
			e.dataTransfer.effectAllowed = 'copy';
			e.dataTransfer.setData('text/plain', '');
		});

		block.addEventListener('dragend', function() {
			draggedBlock = null;
			draggedFromWorkspace = false;
			workspace.classList.remove('drag-over');
		});

		// Double-click to add quickly
		block.addEventListener('dblclick', function() {
			var type = this.getAttribute('data-block-type');

			// Validate placement
			var blocks = workspace.querySelectorAll('.workspace-block');
			if (blocks.length === 0 && (type === 'elif' || type === 'else' || type === 'end')) {
				return;
			}

			var newBlock = createWorkspaceBlock(type, {
				label: this.getAttribute('data-label')
			});
			workspace.appendChild(newBlock);
			syncBlocksToDSL();
		});
	}

	initPaletteDrag();

	// Re-init when labels are dynamically added
	var observer = new MutationObserver(function(mutations) {
		mutations.forEach(function(mutation) {
			if (mutation.addedNodes.length > 0) {
				mutation.addedNodes.forEach(function(node) {
					if (node.classList && node.classList.contains('palette-block')) {
						attachPaletteDrag(node);
					}
				});
			}
		});
	});

	var labelsContainer = document.getElementById('palette_labels_container');
	if (labelsContainer) {
		observer.observe(labelsContainer, { childList: true });
	}

	// ─── Trash zone ─────────────────────────────────────────────────────
	if (trashZone) {
		trashZone.addEventListener('dragover', function(e) {
			e.preventDefault();
			trashZone.classList.add('drag-over');
		});

		trashZone.addEventListener('dragleave', function() {
			trashZone.classList.remove('drag-over');
		});

		trashZone.addEventListener('drop', function(e) {
			e.preventDefault();
			e.stopPropagation();
			trashZone.classList.remove('drag-over');

			if (draggedBlock && draggedFromWorkspace) {
				draggedBlock.remove();
				syncBlocksToDSL();
			}

			draggedBlock = null;
			draggedFromWorkspace = false;
		});
	}

	// ─── Clear drop indicators ──────────────────────────────────────────
	function clearDropIndicators() {
		var blocks = workspace.querySelectorAll('.workspace-block');
		for (var i = 0; i < blocks.length; i++) {
			blocks[i].classList.remove('drop-above', 'drop-below', 'drop-invalid');
		}
	}

	// ─── Load code string into visual blocks ────────────────────────────
	function loadCodeToBlocks(code) {
		// Clear workspace
		var existingBlocks = workspace.querySelectorAll('.workspace-block');
		for (var i = 0; i < existingBlocks.length; i++) {
			existingBlocks[i].remove();
		}

		var lines = code.split('\n');
		for (var i = 0; i < lines.length; i++) {
			var line = lines[i].trim();
			if (line === '' || line.startsWith('#')) continue;

			var blockInfo = lineToBlock(line);
			if (blockInfo) {
				var newBlock = createWorkspaceBlock(blockInfo.type, blockInfo.data);
				workspace.appendChild(newBlock);
			}
		}

		syncBlocksToDSL();
	}

	function lineToBlock(line) {
		// Sensing blocks
		if (line === 'links = leftmost_detection') return { type: 'get_left', data: {} };
		if (line === 'rechts = rightmost_detection') return { type: 'get_right', data: {} };
		if (line === 'anzahl = detection_count') return { type: 'get_count', data: {} };
		if (line === 'oben = topmost_detection') return { type: 'get_top', data: {} };
		if (line === 'unten = bottommost_detection') return { type: 'get_bottom', data: {} };
		if (line === 'groesstes = largest_detection') return { type: 'get_largest', data: {} };
		if (line === 'kleinstes = smallest_detection') return { type: 'get_smallest', data: {} };
		if (line === 'bestes = highest_conf_detection') return { type: 'get_best', data: {} };

		// Control flow with condition parsing
		if (line.startsWith('if ')) {
			var cond = parseConditionString(line.substring(3).trim());
			return { type: 'if', data: { condition: cond } };
		}
		if (line.startsWith('elif ')) {
			var cond = parseConditionString(line.substring(5).trim());
			return { type: 'elif', data: { condition: cond } };
		}
		if (line === 'else') return { type: 'else', data: {} };
		if (line === 'end') return { type: 'end', data: {} };

		// Show text
		if (line.startsWith('show_text ')) {
			var afterCmd = line.substring('show_text '.length).trim();
			var styles = ['normal', 'winner', 'loser', 'draw'];
			var style = 'normal';
			var message = afterCmd;

			var lastSpace = -1;
			var inStr = false, strChar = '';
			for (var i = 0; i < afterCmd.length; i++) {
				var ch = afterCmd[i];
				if (!inStr && (ch === '"' || ch === "'")) { inStr = true; strChar = ch; }
				else if (inStr && ch === strChar) { inStr = false; }
				else if (!inStr && ch === ' ') { lastSpace = i; }
			}
			if (!inStr && lastSpace !== -1) {
				var candidate = afterCmd.substring(lastSpace + 1);
				if (styles.indexOf(candidate) !== -1) {
					style = candidate;
					message = afterCmd.substring(0, lastSpace).trim();
				}
			}
			return { type: 'show_text', data: {}, inputs: [message, style] };
		}

		// Print
		if (line.startsWith('print ') || line.startsWith('print(')) {
			var arg = '';
			if (line.startsWith('print(')) {
				var depth = 0;
				for (var i = 5; i < line.length; i++) {
					if (line[i] === '(') depth++;
					else if (line[i] === ')') { depth--; if (depth === 0) { arg = line.substring(6, i); break; } }
				}
			} else {
				arg = line.substring(6).trim();
			}
			return { type: 'print', data: {}, inputs: [arg] };
		}

		// Variable assignment (generic)
		var assignMatch = line.match(/^([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*(.+)$/);
		if (assignMatch) {
			return { type: 'set_var', data: {}, inputs: [assignMatch[1], assignMatch[2].trim()] };
		}

		return null;
	}

	function parseConditionString(condStr) {
		condStr = condStr.trim();
		var operators = ['==', '!=', '>=', '<=', '>', '<'];
		for (var i = 0; i < operators.length; i++) {
			var op = operators[i];
			var idx = findOpInCondition(condStr, op);
			if (idx !== -1) {
				return {
					left: condStr.substring(0, idx).trim(),
					op: op,
					right: condStr.substring(idx + op.length).trim()
				};
			}
		}
		return { left: condStr, op: '==', right: '"none"' };
	}

	function findOpInCondition(str, op) {
		var inStr = false, strChar = '';
		for (var i = 0; i <= str.length - op.length; i++) {
			var ch = str[i];
			if (!inStr && (ch === '"' || ch === "'")) { inStr = true; strChar = ch; }
			else if (inStr && ch === strChar) { inStr = false; }
			else if (!inStr && str.substring(i, i + op.length) === op) {
				if (op === '>' && i + 1 < str.length && str[i + 1] === '=') continue;
				if (op === '<' && i + 1 < str.length && str[i + 1] === '=') continue;
				if ((op === '=' || op === '==') && i > 0 && (str[i - 1] === '!' || str[i - 1] === '>' || str[i - 1] === '<')) continue;
				return i;
			}
		}
		return -1;
	}

	// ─── Expose loadCodeToBlocks globally ───────────────────────────────
	window.loadCodeToBlocks = loadCodeToBlocks;

	// ─── Initial sync ───────────────────────────────────────────────────
	syncBlocksToDSL();

})();
