// ═══════════════════════════════════════════════════════════════════════════
// VISUAL BLOCK EDITOR — Drag & drop blocks, auto-sync to DSL
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

	// ─── Sync blocks to DSL code ────────────────────────────────────────
	function syncBlocksToDSL() {
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
		var inputs = block.querySelectorAll('input, select');

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
				return 'if ' + (getInputValue(inputs, 0) || 'links == "none"');
			case 'elif':
				return 'elif ' + (getInputValue(inputs, 0) || 'rechts == "none"');
			case 'else':
				return 'else';
			case 'end':
				return 'end';
			case 'print':
				return 'print ' + (getInputValue(inputs, 0) || '"Hallo!"');
			case 'show_text':
				var msg = getInputValue(inputs, 0) || '"Hallo!"';
				var style = getInputValue(inputs, 1) || 'normal';
				return 'show_text ' + msg + ' ' + style;
			case 'set_var':
				var vname = getInputValue(inputs, 0) || 'x';
				var vval = getInputValue(inputs, 1) || '0';
				return vname + ' = ' + vval;
			case 'label_value':
				return null; // Labels are values, not standalone lines
			default:
				return '# unknown block: ' + type;
		}
	}

	function getInputValue(inputs, index) {
		if (inputs && inputs.length > index) return inputs[index].value;
		return null;
	}

	// ─── Create workspace block from type ───────────────────────────────
	function createWorkspaceBlock(type, data) {
		var block = document.createElement('div');
		block.className = 'workspace-block';
		block.setAttribute('data-block-type', type);
		block.setAttribute('draggable', 'true');

		var cat = getCategoryClass(type);
		if (cat) block.classList.add(cat);

		var content = buildBlockContent(type, data);
		block.innerHTML = content;

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

		// Input change listeners
		var inputs = block.querySelectorAll('input, select');
		for (var i = 0; i < inputs.length; i++) {
			inputs[i].addEventListener('input', syncBlocksToDSL);
			inputs[i].addEventListener('change', syncBlocksToDSL);
		}

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
			clearDropIndicators();
			var rect = this.getBoundingClientRect();
			var midY = rect.top + rect.height / 2;
			if (e.clientY < midY) {
				this.classList.add('drop-above');
			} else {
				this.classList.add('drop-below');
			}
		});

		block.addEventListener('dragleave', function() {
			this.classList.remove('drop-above', 'drop-below');
		});

		block.addEventListener('drop', function(e) {
			e.preventDefault();
			e.stopPropagation();
			if (!draggedBlock || draggedBlock === this) {
				clearDropIndicators();
				return;
			}

			var rect = this.getBoundingClientRect();
			var midY = rect.top + rect.height / 2;
			var insertBefore = e.clientY < midY;

			if (draggedFromWorkspace) {
				// Reorder
				if (insertBefore) {
					workspace.insertBefore(draggedBlock, this);
				} else {
					workspace.insertBefore(draggedBlock, this.nextSibling);
				}
			} else {
				// New block from palette
				var newBlock = createWorkspaceBlock(
					draggedBlock.getAttribute('data-block-type'),
					{ label: draggedBlock.getAttribute('data-label') }
				);
				if (insertBefore) {
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

	function buildBlockContent(type, data) {
		switch (type) {
			case 'get_left':
				return '🎯 <strong>links</strong> = was links ist';
			case 'get_right':
				return '🎯 <strong>rechts</strong> = was rechts ist';
			case 'get_count':
				return '🔢 <strong>anzahl</strong> = wie viele?';
			case 'get_top':
				return '🎯 <strong>oben</strong> = was oben ist';
			case 'get_bottom':
				return '🎯 <strong>unten</strong> = was unten ist';
			case 'get_largest':
				return '🎯 <strong>größtes</strong> = größte Erkennung';
			case 'get_smallest':
				return '🎯 <strong>kleinstes</strong> = kleinste Erkennung';
			case 'get_best':
				return '🎯 <strong>bestes</strong> = sicherste Erkennung';
			case 'if':
				return '🔶 wenn <input type="text" value=\'links == "none"\' placeholder="Bedingung" style="width:180px;"> dann';
			case 'elif':
				return '🔷 sonst wenn <input type="text" value=\'rechts == "none"\' placeholder="Bedingung" style="width:150px;"> dann';
			case 'else':
				return '⬜ sonst';
			case 'end':
				return '🔚 ende';
			case 'print':
				return '💬 sag <input type="text" value=\'"Hallo!"\' placeholder="Text / Variable" style="width:180px;">';
			case 'show_text':
				return '📺 zeige <input type="text" value=\'"Hallo!"\' placeholder="Text / Variable" style="width:140px;"> '
					+ '<select><option value="normal">normal</option><option value="winner">🎉 Gewinner</option>'
					+ '<option value="loser">😢 Verlierer</option><option value="draw">🤝 Gleich</option></select>';
			case 'set_var':
				return '📦 setze <input type="text" value="x" placeholder="Name" style="width:60px;"> = '
					+ '<input type="text" value="0" placeholder="Wert" style="width:100px;">';
			case 'label_value':
				var label = (data && data.label) || '???';
				return '🏷️ <strong>"' + label + '"</strong>';
			default:
				return '❓ Unbekannt';
		}
	}

	// ─── Workspace drop zone ────────────────────────────────────────────
	workspace.addEventListener('dragover', function(e) {
		e.preventDefault();
		workspace.classList.add('drag-over');
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

		if (draggedFromWorkspace) {
			// Already handled by block-level drop
			syncBlocksToDSL();
			return;
		}

		// New block from palette
		var type = draggedBlock.getAttribute('data-block-type');
		var newBlock = createWorkspaceBlock(type, {
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
			blocks[i].classList.remove('drop-above', 'drop-below');
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
				// Set input values if provided
				if (blockInfo.inputs) {
					var inputs = newBlock.querySelectorAll('input, select');
					for (var j = 0; j < blockInfo.inputs.length && j < inputs.length; j++) {
						inputs[j].value = blockInfo.inputs[j];
					}
				}
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

		// Control flow
		if (line.startsWith('if ')) return { type: 'if', data: {}, inputs: [line.substring(3).trim()] };
		if (line.startsWith('elif ')) return { type: 'elif', data: {}, inputs: [line.substring(5).trim()] };
		if (line === 'else') return { type: 'else', data: {} };
		if (line === 'end') return { type: 'end', data: {} };

		// Show text
		if (line.startsWith('show_text ')) {
			var afterCmd = line.substring('show_text '.length).trim();
			var styles = ['normal', 'winner', 'loser', 'draw'];
			var style = 'normal';
			var message = afterCmd;

			// Try to extract style from end
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

	// ─── Expose loadCodeToBlocks globally ───────────────────────────────
	window.loadCodeToBlocks = loadCodeToBlocks;

	// ─── Initial sync ───────────────────────────────────────────────────
	syncBlocksToDSL();

})();
