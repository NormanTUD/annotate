// ═══════════════════════════════════════════════════════════════════════════
// VISUAL BLOCKS ENGINE (inline, enhanced for new UI)
// ═══════════════════════════════════════════════════════════════════════════
(function() {
	"use strict";

	// ─── Model Labels ───────────────────────────────────────────────────
	var modelLabels = [];

	function formatValueForDSL(val) {
		if (val === undefined || val === null || val === '') return '"none"';
		val = String(val);
		if ((val.startsWith('"') && val.endsWith('"')) ||
			(val.startsWith("'") && val.endsWith("'"))) return val;
		if (!isNaN(val) && val.trim() !== '') return val;
		if (modelLabels.indexOf(val) !== -1) return '"' + val + '"';
		if (/^[a-zA-Z_][a-zA-Z0-9_]*$/.test(val)) return val;
		return '"' + val + '"';
	}

	// ─── Block Definitions ──────────────────────────────────────────────
	var BLOCK_DEFS = {
		get_left: {
			category: 'sensing', color: '#4fc3f7', label: '🎯',
			fields: [
				{ type: 'input', key: 'varname', default: 'links', placeholder: 'Variablenname' }
			],
			labelAfter: '= was links ist',
			toDSL: function(f) { return (f.varname || 'links') + ' = leftmost_detection'; }
		},
		get_right: {
			category: 'sensing', color: '#4fc3f7', label: '🎯',
			fields: [
				{ type: 'input', key: 'varname', default: 'rechts', placeholder: 'Variablenname' }
			],
			labelAfter: '= was rechts ist',
			toDSL: function(f) { return (f.varname || 'rechts') + ' = rightmost_detection'; }
		},
		get_count: {
			category: 'sensing', color: '#4fc3f7', label: '🔢',
			fields: [
				{ type: 'input', key: 'varname', default: 'anzahl', placeholder: 'Variablenname' }
			],
			labelAfter: '= wie viele Hände?',
			toDSL: function(f) { return (f.varname || 'anzahl') + ' = detection_count'; }
		},
		'if': {
			category: 'control', color: '#ffb74d', label: '🔶 wenn',
			fields: [
				{ type: 'label_or_var', key: 'left_val', default: 'links', placeholder: 'Variable' },
				{ type: 'select', key: 'operator', options: ['==','!=','>','<','>=','<='], default: '==' },
				{ type: 'label_or_input', key: 'right_val', default: '', placeholder: 'Wert' }
			],
			labelAfter: 'dann',
			canHaveSecondCondition: true,
			toDSL: function(f) {
				var c = (f.left_val||'links') + ' ' + (f.operator||'==') + ' ' + formatValueForDSL(f.right_val);
				if (f.logic && f.left_val2) {
					c += ' ' + f.logic + ' ' + (f.left_val2||'rechts') + ' ' + (f.operator2||'==') + ' ' + formatValueForDSL(f.right_val2);
				}
				return 'if ' + c;
			}
		},
		'elif': {
			category: 'control', color: '#ffa726', label: '🔷 sonst wenn',
			fields: [
				{ type: 'label_or_var', key: 'left_val', default: 'links', placeholder: 'Variable' },
				{ type: 'select', key: 'operator', options: ['==','!=','>','<','>=','<='], default: '==' },
				{ type: 'label_or_input', key: 'right_val', default: '', placeholder: 'Wert' }
			],
			labelAfter: 'dann',
			canHaveSecondCondition: true,
			toDSL: function(f) {
				var c = (f.left_val||'links') + ' ' + (f.operator||'==') + ' ' + formatValueForDSL(f.right_val);
				if (f.logic && f.left_val2) {
					c += ' ' + f.logic + ' ' + (f.left_val2||'rechts') + ' ' + (f.operator2||'==') + ' ' + formatValueForDSL(f.right_val2);
				}
				return 'elif ' + c;
			}
		},
		'else': {
			category: 'control', color: '#78909c', label: '⬜ sonst',
			fields: [],
			toDSL: function() { return 'else'; }
		},
		'end': {
			category: 'control', color: '#90a4ae', label: '🔚 ende',
			fields: [],
			toDSL: function() { return 'end'; }
		},
		'print': {
			category: 'output', color: '#ba68c8', label: '💬 sag',
			fields: [
				{ type: 'input', key: 'message', default: '"Hallo!"', placeholder: '"Nachricht" oder Variable', wide: true }
			],
			toDSL: function(f) { return 'print ' + (f.message || '""'); }
		},
		'show_text': {
			category: 'display', color: '#4dd0e1', label: '📺 zeige auf Bild',
			fields: [
				{ type: 'input', key: 'message', default: '"Bereit!"', placeholder: '"Text" oder Variable', wide: true },
				{ type: 'select', key: 'style', options: ['normal','winner','loser','draw'], default: 'normal' }
			],
			toDSL: function(f) { return 'show_text ' + (f.message || '""') + ' ' + (f.style || 'normal'); }
		},
		'set_var': {
			category: 'variables', color: '#e57373', label: '📦 setze',
			fields: [
				{ type: 'input', key: 'varname', default: 'x', placeholder: 'Name' }
			],
			labelMid: '=',
			fields2: [
				{ type: 'label_or_input', key: 'value', default: '', placeholder: 'Wert', wide: true }
			],
			toDSL: function(f) {
				return (f.varname || 'x') + ' = ' + formatValueForDSL(f.value);
			}
		}
	};

	// ─── State ──────────────────────────────────────────────────────────
	var workspaceBlocks = [];
	var blockIdCounter = 0;
	var draggedBlockType = null;
	var draggedWorkspaceIdx = null;

	var workspace   = document.getElementById('block_workspace');
	var placeholder = document.getElementById('workspace_placeholder');
	var trashZone   = document.getElementById('trash_zone');
	var dslEditor   = document.getElementById('dsl_editor');

	function newBlockId() { return 'block_' + (++blockIdCounter); }

	// ─── Collect variable names ─────────────────────────────────────────
	function collectVariableNames() {
		var names = [], seen = {};
		for (var i = 0; i < workspaceBlocks.length; i++) {
			var b = workspaceBlocks[i], vn = null;
			if (b.type === 'get_left' || b.type === 'get_right' || b.type === 'get_count') {
				vn = b.fields.varname;
			} else if (b.type === 'set_var') {
				vn = b.fields.varname;
			}
			if (vn && !seen[vn]) { seen[vn] = true; names.push(vn); }
		}
		['links', 'rechts', 'anzahl'].forEach(function(d) {
			if (!seen[d]) names.push(d);
		});
		return names;
	}

	// ─── Fetch model labels ─────────────────────────────────────────────
	function fetchModelLabels(modelUuid) {
		if (!modelUuid || modelUuid === 'none') {
			modelLabels = [];
			updateLabelUI();
			updateStepIndicators();
			return;
		}
		fetch('labels.php?model_uuid=' + encodeURIComponent(modelUuid))
			.then(function(r) { return r.ok ? r.json() : []; })
			.then(function(labels) {
				modelLabels = Array.isArray(labels) ? labels : [];
				updateLabelUI();
				updateStepIndicators();
			})
			.catch(function() {
				modelLabels = [];
				updateLabelUI();
				updateStepIndicators();
			});
	}

	// ─── Update label UI ────────────────────────────────────────────────
	function updateLabelUI() {
		var infoBar = document.getElementById('model_labels_info');
		var chips   = document.getElementById('model_labels_chips');
		if (infoBar && chips) {
			if (modelLabels.length > 0) {
				infoBar.classList.add('visible');
				chips.innerHTML = '';
				modelLabels.forEach(function(label) {
					var chip = document.createElement('span');
					chip.className = 'label-chip';
					chip.textContent = label;
					chips.appendChild(chip);
				});
			} else {
				infoBar.classList.remove('visible');
				chips.innerHTML = '';
			}
		}

		var paletteCat = document.getElementById('palette_labels_category');
		var paletteCon = document.getElementById('palette_labels_container');
		if (paletteCat && paletteCon) {
			if (modelLabels.length > 0) {
				paletteCat.style.display = 'block';
				paletteCon.innerHTML = '';
				modelLabels.forEach(function(label) {
					var block = document.createElement('div');
					block.className = 'palette-block cat-labels';
					block.setAttribute('data-block-type', 'label_value');
					block.setAttribute('data-label-value', label);
					block.setAttribute('draggable', 'true');
					block.textContent = '🏷️ "' + label + '"';
					block.addEventListener('dragstart', function(e) {
						draggedBlockType = 'label_value:' + label;
						draggedWorkspaceIdx = null;
						trashZone.classList.add('visible');
						e.dataTransfer.effectAllowed = 'copy';
						e.dataTransfer.setData('text/plain', 'palette:label_value:' + label);
					});
					block.addEventListener('dragend', function() {
						draggedBlockType = null;
						trashZone.classList.remove('visible');
						trashZone.classList.remove('hover');
					});
					paletteCon.appendChild(block);
				});
			} else {
				paletteCat.style.display = 'none';
				paletteCon.innerHTML = '';
			}
		}
		renderWorkspace();
	}

	// ─── Step indicators ────────────────────────────────────────────────
	function updateStepIndicators() {
		var step1 = document.getElementById('step1_indicator');
		var step2 = document.getElementById('step2_indicator');
		var step3 = document.getElementById('step3_indicator');

		var modelSelected = document.getElementById('game_model_select').value !== 'none';
		var hasBlocks = workspaceBlocks.length > 0;

		if (step1) {
			step1.className = 'step-item' + (modelSelected ? ' done' : ' active');
		}
		if (step2) {
			step2.className = 'step-item' + (hasBlocks ? ' done' : (modelSelected ? ' active' : ''));
		}
		if (step3) {
			step3.className = 'step-item' + (hasBlocks && modelSelected ? ' active' : '');
		}
	}

	// Listen for model changes
	var modelSelect = document.getElementById('game_model_select');
	if (modelSelect) {
		modelSelect.addEventListener('change', function() {
			fetchModelLabels(this.value);
			// Update help text
			var help = document.getElementById('setup_help');
			if (help && this.value !== 'none') {
				help.innerHTML = '<span class="emoji-big">🎉</span> <strong>Super!</strong> Modell gewählt! Jetzt ziehe Blöcke in den Arbeitsbereich oder klicke "Beispiel laden" für einen Schnellstart.';
			}
		});
		if (modelSelect.value && modelSelect.value !== 'none') {
			fetchModelLabels(modelSelect.value);
		}
	}

	// ─── Render workspace ───────────────────────────────────────────────
	function renderWorkspace() {
		var existing = workspace.querySelectorAll('.workspace-block, .drop-indicator');
		existing.forEach(function(el) { el.remove(); });

		placeholder.style.display = workspaceBlocks.length === 0 ? 'block' : 'none';

		var indent = 0;
		for (var i = 0; i < workspaceBlocks.length; i++) {
			var block = workspaceBlocks[i];
			var def = BLOCK_DEFS[block.type];
			if (!def) continue;

			if (block.type === 'elif' || block.type === 'else' || block.type === 'end') {
				indent = Math.max(0, indent - 1);
			}

			var el = createBlockElement(block, i, indent);
			workspace.insertBefore(el, trashZone);

			if (block.type === 'if' || block.type === 'elif' || block.type === 'else') {
				indent++;
			}
		}
		generateDSL();
		updateStepIndicators();
	}

	// ─── Create block element ───────────────────────────────────────────
	function createBlockElement(block, index, indentLevel) {
		var def = BLOCK_DEFS[block.type];
		var el = document.createElement('div');
		el.className = 'workspace-block';
		if (indentLevel > 0) el.classList.add('indent-' + Math.min(indentLevel, 3));
		el.style.background = def.color;
		el.setAttribute('data-index', index);
		el.setAttribute('draggable', 'true');

		// Delete button
		var delBtn = document.createElement('div');
		delBtn.className = 'block-delete';
		delBtn.textContent = '✕';
		delBtn.addEventListener('click', function(e) {
			e.stopPropagation();
			workspaceBlocks.splice(index, 1);
			renderWorkspace();
		});
		el.appendChild(delBtn);

		// Label
		var labelSpan = document.createElement('span');
		labelSpan.textContent = def.label + ' ';
		el.appendChild(labelSpan);

		// Fields
		if (def.fields) {
			def.fields.forEach(function(fd) { el.appendChild(createFieldElement(block, fd)); });
		}

		if (def.labelAfter) {
			var s = document.createElement('span');
			s.textContent = ' ' + def.labelAfter;
			el.appendChild(s);
		}

		if (def.labelMid) {
			var m = document.createElement('span');
			m.textContent = ' ' + def.labelMid + ' ';
			el.appendChild(m);
		}

		if (def.fields2) {
			def.fields2.forEach(function(fd) { el.appendChild(createFieldElement(block, fd)); });
		}

		// Second condition toggle for if/elif
		if (def.canHaveSecondCondition) {
			var addBtn = document.createElement('button');
			addBtn.textContent = block.fields.logic ? '➖' : '➕';
			addBtn.style.cssText = 'background:rgba(0,0,0,0.2);border:1px solid rgba(255,255,255,0.3);border-radius:50%;width:22px;height:22px;color:#fff;cursor:pointer;font-size:11px;margin-left:4px;padding:0;';
			addBtn.title = block.fields.logic ? 'Zweite Bedingung entfernen' : 'Zweite Bedingung hinzufügen';
			addBtn.addEventListener('click', function(e) {
				e.stopPropagation();
				if (block.fields.logic) {
					delete block.fields.logic;
					delete block.fields.left_val2;
					delete block.fields.operator2;
					delete block.fields.right_val2;
				} else {
					block.fields.logic = 'and';
					block.fields.left_val2 = 'rechts';
					block.fields.operator2 = '==';
					block.fields.right_val2 = modelLabels.length > 0 ? modelLabels[0] : '';
				}
				renderWorkspace();
			});
			el.appendChild(addBtn);

			if (block.fields.logic) {
				var logicSel = document.createElement('select');
				logicSel.className = 'block-select';
				logicSel.style.marginLeft = '4px';
				['and', 'or'].forEach(function(opt) {
					var o = document.createElement('option');
					o.value = opt; o.textContent = opt === 'and' ? 'UND' : 'ODER';
					if (block.fields.logic === opt) o.selected = true;
					logicSel.appendChild(o);
				});
				logicSel.addEventListener('change', function() {
					block.fields.logic = this.value;
					generateDSL();
				});
				logicSel.addEventListener('mousedown', function(e) { e.stopPropagation(); });
				el.appendChild(logicSel);

				var c2 = [
					{ type: 'label_or_var', key: 'left_val2', default: 'rechts', placeholder: 'Variable' },
					{ type: 'select', key: 'operator2', options: ['==','!=','>','<','>=','<='], default: '==' },
					{ type: 'label_or_input', key: 'right_val2', default: modelLabels.length > 0 ? modelLabels[0] : '', placeholder: 'Wert' }
				];
				c2.forEach(function(fd) { el.appendChild(createFieldElement(block, fd)); });
			}
		}

		// Drag events for reordering
		el.addEventListener('dragstart', function(e) {
			draggedWorkspaceIdx = index;
			draggedBlockType = null;
			el.classList.add('dragging');
			trashZone.classList.add('visible');
			e.dataTransfer.effectAllowed = 'move';
			e.dataTransfer.setData('text/plain', 'workspace:' + index);
		});
		el.addEventListener('dragend', function() {
			el.classList.remove('dragging');
			trashZone.classList.remove('visible');
			trashZone.classList.remove('hover');
			draggedWorkspaceIdx = null;
			workspace.querySelectorAll('.drop-indicator').forEach(function(ind) { ind.remove(); });
		});
		el.addEventListener('dragover', function(e) {
			e.preventDefault();
			e.dataTransfer.dropEffect = 'move';
		});
		el.addEventListener('drop', function(e) {
			e.preventDefault();
			e.stopPropagation();
			handleDrop(parseInt(el.getAttribute('data-index')), e);
		});

		return el;
	}

	// ─── Create field element ───────────────────────────────────────────
	function createFieldElement(block, fieldDef) {
		if (fieldDef.type === 'label_or_input') {
			if (modelLabels.length > 0) {
				var wrapper = document.createElement('span');
				wrapper.style.cssText = 'display:inline-flex;align-items:center;gap:2px;';
				var cur = block.fields[fieldDef.key] !== undefined ? block.fields[fieldDef.key] : (fieldDef.default || '');
				if (block.fields[fieldDef.key] === undefined) block.fields[fieldDef.key] = fieldDef.default || '';
				var isKnown = (modelLabels.indexOf(cur) !== -1) || cur === 'none' || cur === '';
				var isCustom = !isKnown && cur !== '';

				var sel = document.createElement('select');
				sel.className = 'block-select';
				var noneOpt = document.createElement('option');
				noneOpt.value = 'none'; noneOpt.textContent = '— keine —';
				if (cur === '' || cur === 'none') noneOpt.selected = true;
				sel.appendChild(noneOpt);
				modelLabels.forEach(function(label) {
					var o = document.createElement('option');
					o.value = label; o.textContent = '🏷️ ' + label;
					if (cur === label) o.selected = true;
					sel.appendChild(o);
				});
				var custOpt = document.createElement('option');
				custOpt.value = '__custom__'; custOpt.textContent = '✏️ eigener Wert...';
				if (isCustom) custOpt.selected = true;
				sel.appendChild(custOpt);

				var custIn = document.createElement('input');
				custIn.type = 'text';
				custIn.className = 'block-input';
				custIn.placeholder = 'Wert eingeben...';
				custIn.value = isCustom ? cur : '';
				custIn.style.display = isCustom ? 'inline-block' : 'none';
				custIn.style.minWidth = fieldDef.wide ? '120px' : '80px';

				sel.addEventListener('change', function() {
					if (this.value === '__custom__') {
						custIn.style.display = 'inline-block';
						custIn.focus();
						block.fields[fieldDef.key] = custIn.value || '';
					} else {
						custIn.style.display = 'none';
						block.fields[fieldDef.key] = this.value;
					}
					generateDSL();
				});
				custIn.addEventListener('input', function() {
					block.fields[fieldDef.key] = this.value;
					generateDSL();
				});
				sel.addEventListener('mousedown', function(e) { e.stopPropagation(); });
				custIn.addEventListener('mousedown', function(e) { e.stopPropagation(); });
				custIn.addEventListener('dragstart', function(e) { e.preventDefault(); e.stopPropagation(); });
				wrapper.appendChild(sel);
				wrapper.appendChild(custIn);
				return wrapper;
			}
			return createPlainInput(block, fieldDef);
		}

		if (fieldDef.type === 'label_or_var') {
			var wrapper = document.createElement('span');
			wrapper.style.cssText = 'display:inline-flex;align-items:center;gap:2px;';
			var cur = block.fields[fieldDef.key] !== undefined ? block.fields[fieldDef.key] : (fieldDef.default || '');
			if (block.fields[fieldDef.key] === undefined) block.fields[fieldDef.key] = fieldDef.default || '';
			var varNames = collectVariableNames();
			var isKnown = varNames.indexOf(cur) !== -1;
			var isCustom = !isKnown && cur !== '';

			var sel = document.createElement('select');
			sel.className = 'block-select';
			if (varNames.length === 0) {
				var emptyOpt = document.createElement('option');
				emptyOpt.value = ''; emptyOpt.textContent = '(keine Variablen)';
				sel.appendChild(emptyOpt);
			} else {
				varNames.forEach(function(vn) {
					var o = document.createElement('option');
					o.value = vn; o.textContent = '📦 ' + vn;
					if (cur === vn) o.selected = true;
					sel.appendChild(o);
				});
			}
			var custOpt = document.createElement('option');
			custOpt.value = '__custom__'; custOpt.textContent = '✏️ eigener...';
			if (isCustom) custOpt.selected = true;
			sel.appendChild(custOpt);

			var custIn = document.createElement('input');
			custIn.type = 'text';
			custIn.className = 'block-input';
			custIn.placeholder = 'Name...';
			custIn.value = isCustom ? cur : '';
			custIn.style.display = isCustom ? 'inline-block' : 'none';
			custIn.style.minWidth = '70px';

			sel.addEventListener('change', function() {
				if (this.value === '__custom__') {
					custIn.style.display = 'inline-block';
					custIn.focus();
					block.fields[fieldDef.key] = custIn.value || '';
				} else {
					custIn.style.display = 'none';
					block.fields[fieldDef.key] = this.value;
				}
				generateDSL();
			});
			custIn.addEventListener('input', function() {
				block.fields[fieldDef.key] = this.value;
				generateDSL();
			});
			sel.addEventListener('mousedown', function(e) { e.stopPropagation(); });
			custIn.addEventListener('mousedown', function(e) { e.stopPropagation(); });
			custIn.addEventListener('dragstart', function(e) { e.preventDefault(); e.stopPropagation(); });
			wrapper.appendChild(sel);
			wrapper.appendChild(custIn);
			return wrapper;
		}

		if (fieldDef.type === 'input' || fieldDef.type === 'condition') {
			return createPlainInput(block, fieldDef);
		}

		if (fieldDef.type === 'select') {
			var sel = document.createElement('select');
			sel.className = 'block-select';
			if (block.fields[fieldDef.key] === undefined) {
				block.fields[fieldDef.key] = fieldDef.default || fieldDef.options[0];
			}
			fieldDef.options.forEach(function(opt) {
				var o = document.createElement('option');
				o.value = opt; o.textContent = opt;
				if (block.fields[fieldDef.key] === opt) o.selected = true;
				sel.appendChild(o);
			});
			sel.addEventListener('change', function() {
				block.fields[fieldDef.key] = this.value;
				generateDSL();
			});
			sel.addEventListener('mousedown', function(e) { e.stopPropagation(); });
			return sel;
		}

		var span = document.createElement('span');
		span.textContent = fieldDef.default || '';
		return span;
	}

	function createPlainInput(block, fieldDef) {
		var input = document.createElement('input');
		input.type = 'text';
		input.className = 'block-input';
		input.placeholder = fieldDef.placeholder || '';
		input.value = block.fields[fieldDef.key] !== undefined ? block.fields[fieldDef.key] : (fieldDef.default || '');
		if (fieldDef.wide) input.style.minWidth = '140px';
		if (block.fields[fieldDef.key] === undefined) block.fields[fieldDef.key] = fieldDef.default || '';
		input.addEventListener('input', function() {
			block.fields[fieldDef.key] = this.value;
			generateDSL();
		});
		input.addEventListener('mousedown', function(e) { e.stopPropagation(); });
		input.addEventListener('dragstart', function(e) { e.preventDefault(); e.stopPropagation(); });
		return input;
	}

	// ─── Handle drop ────────────────────────────────────────────────────
	function handleDrop(targetIndex, e) {
		workspace.querySelectorAll('.drop-indicator').forEach(function(ind) { ind.remove(); });
		if (draggedWorkspaceIdx !== null) {
			if (draggedWorkspaceIdx === targetIndex) return;
			var moved = workspaceBlocks.splice(draggedWorkspaceIdx, 1)[0];
			var ins = targetIndex;
			if (draggedWorkspaceIdx < targetIndex) ins--;
			var rect = e.currentTarget ? e.currentTarget.getBoundingClientRect() : null;
			if (rect && e.clientY > rect.top + rect.height / 2) ins++;
			ins = Math.max(0, Math.min(ins, workspaceBlocks.length));
			workspaceBlocks.splice(ins, 0, moved);
			draggedWorkspaceIdx = null;
			renderWorkspace();
			return;
		}
		if (draggedBlockType) {
			var nb = createNewBlock(draggedBlockType);
			if (nb) {
				var ins = targetIndex;
				var rect = e.currentTarget ? e.currentTarget.getBoundingClientRect() : null;
				if (rect && e.clientY > rect.top + rect.height / 2) ins++;
				workspaceBlocks.splice(ins, 0, nb);
				renderWorkspace();
			}
			draggedBlockType = null;
		}
	}

	// ─── Create new block ───────────────────────────────────────────────
	function createNewBlock(type) {
		if (type && type.startsWith('label_value:')) {
			var labelName = type.substring('label_value:'.length);
			return { id: newBlockId(), type: 'set_var', fields: { varname: 'label', value: labelName } };
		}
		var def = BLOCK_DEFS[type];
		if (!def) return null;
		var fields = {};
		if (def.fields) {
			def.fields.forEach(function(f) {
				if (f.type === 'label_or_input' && modelLabels.length > 0) {
					fields[f.key] = f.default || modelLabels[0];
				} else {
					fields[f.key] = f.default || '';
				}
			});
		}
		if (def.fields2) {
			def.fields2.forEach(function(f) {
				if (f.type === 'label_or_input' && modelLabels.length > 0) {
					fields[f.key] = f.default || modelLabels[0];
				} else {
					fields[f.key] = f.default || '';
				}
			});
		}

		return {
			id: newBlockId(),
			type: type,
			fields: fields
		};
	}

	// ─── Generate DSL code from blocks ──────────────────────────────────
	function generateDSL() {
		var lines = [];
		for (var i = 0; i < workspaceBlocks.length; i++) {
			var block = workspaceBlocks[i];
			var def = BLOCK_DEFS[block.type];
			if (!def || !def.toDSL) continue;
			lines.push(def.toDSL(block.fields));
		}
		var code = lines.join('\n');
		dslEditor.value = code;
		return code;
	}

	// ─── Palette drag events ────────────────────────────────────────────
	function bindPaletteDragEvents() {
		var paletteBlocks = document.querySelectorAll('#block_palette .palette-block');
		paletteBlocks.forEach(function(paletteEl) {
			if (paletteEl.getAttribute('data-block-type') === 'label_value') return;
			paletteEl.addEventListener('dragstart', function(e) {
				draggedBlockType = paletteEl.getAttribute('data-block-type');
				draggedWorkspaceIdx = null;
				trashZone.classList.add('visible');
				e.dataTransfer.effectAllowed = 'copy';
				e.dataTransfer.setData('text/plain', 'palette:' + draggedBlockType);
			});
			paletteEl.addEventListener('dragend', function() {
				draggedBlockType = null;
				trashZone.classList.remove('visible');
				trashZone.classList.remove('hover');
			});
		});
	}
	bindPaletteDragEvents();

	// ─── Workspace drag/drop events ─────────────────────────────────────
	workspace.addEventListener('dragover', function(e) {
		e.preventDefault();
		workspace.classList.add('drag-over');
		e.dataTransfer.dropEffect = (draggedWorkspaceIdx !== null) ? 'move' : 'copy';
	});

	workspace.addEventListener('dragleave', function() {
		workspace.classList.remove('drag-over');
	});

	workspace.addEventListener('drop', function(e) {
		e.preventDefault();
		workspace.classList.remove('drag-over');
		if (e.target === workspace || e.target === placeholder) {
			if (draggedBlockType) {
				var newBlock = createNewBlock(draggedBlockType);
				if (newBlock) {
					workspaceBlocks.push(newBlock);
					renderWorkspace();
				}
				draggedBlockType = null;
			} else if (draggedWorkspaceIdx !== null) {
				var movedBlock = workspaceBlocks.splice(draggedWorkspaceIdx, 1)[0];
				workspaceBlocks.push(movedBlock);
				draggedWorkspaceIdx = null;
				renderWorkspace();
			}
		}
	});

	// ─── Trash zone ─────────────────────────────────────────────────────
	trashZone.addEventListener('dragover', function(e) {
		e.preventDefault();
		e.stopPropagation();
		trashZone.classList.add('hover');
		e.dataTransfer.dropEffect = 'move';
	});

	trashZone.addEventListener('dragleave', function() {
		trashZone.classList.remove('hover');
	});

	trashZone.addEventListener('drop', function(e) {
		e.preventDefault();
		e.stopPropagation();
		trashZone.classList.remove('hover');
		trashZone.classList.remove('visible');
		if (draggedWorkspaceIdx !== null) {
			workspaceBlocks.splice(draggedWorkspaceIdx, 1);
			draggedWorkspaceIdx = null;
			renderWorkspace();
		}
		draggedBlockType = null;
	});

	// ─── Show code button ───────────────────────────────────────────────
	var showCodeBtn = document.getElementById('btn_show_code');
	if (showCodeBtn) {
		showCodeBtn.addEventListener('click', function() {
			var code = generateDSL();
			document.getElementById('code_preview_content').textContent = code || '(noch keine Blöcke)';
			document.getElementById('code_preview_modal').classList.add('visible');
		});
	}

	// ─── Close modal on background click ────────────────────────────────
	var modal = document.getElementById('code_preview_modal');
	if (modal) {
		modal.addEventListener('click', function(e) {
			if (e.target === modal) modal.classList.remove('visible');
		});
	}

	// ─── Load Example Button ────────────────────────────────────────────
	var loadExampleBtn = document.getElementById('btn_load_example');
	if (loadExampleBtn) {
		loadExampleBtn.addEventListener('click', function() {
			loadDefaultExample();
		});
	}

	function loadDefaultExample() {
		workspaceBlocks = [];

		var l1 = modelLabels.length > 0 ? modelLabels[0] : 'KategorieA';
		var l2 = modelLabels.length > 1 ? modelLabels[1] : 'KategorieB';

		// anzahl = detection_count
		workspaceBlocks.push({ id: newBlockId(), type: 'get_count', fields: { varname: 'anzahl' } });
		workspaceBlocks.push({ id: newBlockId(), type: 'get_left', fields: { varname: 'links' } });
		workspaceBlocks.push({ id: newBlockId(), type: 'get_right', fields: { varname: 'rechts' } });

		// if anzahl == 0 → nichts erkannt
		workspaceBlocks.push({ id: newBlockId(), type: 'if', fields: { left_val: 'anzahl', operator: '==', right_val: '0' } });
		workspaceBlocks.push({ id: newBlockId(), type: 'show_text', fields: { message: '"Nichts erkannt – halte etwas in die Kamera!"', style: 'normal' } });

		// elif anzahl == 1 → eine Erkennung
		workspaceBlocks.push({ id: newBlockId(), type: 'elif', fields: { left_val: 'anzahl', operator: '==', right_val: '1' } });

		// Check for first label
		workspaceBlocks.push({ id: newBlockId(), type: 'if', fields: { left_val: 'links', operator: '==', right_val: l1 } });
		workspaceBlocks.push({ id: newBlockId(), type: 'show_text', fields: { message: '"Ich sehe: ' + l1 + '! 👍"', style: 'winner' } });

		if (modelLabels.length > 1) {
			workspaceBlocks.push({ id: newBlockId(), type: 'elif', fields: { left_val: 'links', operator: '==', right_val: l2 } });
			workspaceBlocks.push({ id: newBlockId(), type: 'show_text', fields: { message: '"Ich sehe: ' + l2 + '! ✨"', style: 'winner' } });
		}

		workspaceBlocks.push({ id: newBlockId(), type: 'else', fields: {} });
		workspaceBlocks.push({ id: newBlockId(), type: 'show_text', fields: { message: '"Erkannt: " + links', style: 'normal' } });
		workspaceBlocks.push({ id: newBlockId(), type: 'end', fields: {} }); // end inner if

		// elif anzahl == 2 → zwei Erkennungen, vergleichen
		workspaceBlocks.push({ id: newBlockId(), type: 'elif', fields: { left_val: 'anzahl', operator: '==', right_val: '2' } });

		workspaceBlocks.push({ id: newBlockId(), type: 'if', fields: { left_val: 'links', operator: '==', right_val: 'rechts' } });
		workspaceBlocks.push({ id: newBlockId(), type: 'show_text', fields: { message: '"Beide gleich: " + links + " 🤝"', style: 'draw' } });

		workspaceBlocks.push({ id: newBlockId(), type: 'else', fields: {} });
		workspaceBlocks.push({ id: newBlockId(), type: 'show_text', fields: { message: '"Links: " + links + " | Rechts: " + rechts', style: 'normal' } });

		workspaceBlocks.push({ id: newBlockId(), type: 'end', fields: {} }); // end inner if

		// else → viele Erkennungen
		workspaceBlocks.push({ id: newBlockId(), type: 'else', fields: {} });
		workspaceBlocks.push({ id: newBlockId(), type: 'show_text', fields: { message: '"Ich sehe " + anzahl + " Objekte!"', style: 'normal' } });

		// end outer if
		workspaceBlocks.push({ id: newBlockId(), type: 'end', fields: {} });

		renderWorkspace();
	}

	// ─── Initialize ─────────────────────────────────────────────────────
	workspaceBlocks = [];
	renderWorkspace();

})();

