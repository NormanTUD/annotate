(function() {
	"use strict";

	// ═══════════════════════════════════════════════════════════════════
	// MODEL LABELS — populated when user selects a model
	// ═══════════════════════════════════════════════════════════════════
	var modelLabels = [];  // e.g. ["Stein", "Schere", "Papier"]

	// ═══════════════════════════════════════════════════════════════════
	// HELPER: format a value for DSL output
	// ═══════════════════════════════════════════════════════════════════
	function formatValueForDSL(val) {
		if (val === undefined || val === null || val === '') return '"none"';
		val = String(val);
		// Already quoted
		if ((val.startsWith('"') && val.endsWith('"')) ||
			(val.startsWith("'") && val.endsWith("'"))) {
			return val;
		}
		// Number
		if (!isNaN(val) && val.trim() !== '') return val;
		// Known model label → wrap in quotes
		if (modelLabels.indexOf(val) !== -1) return '"' + val + '"';
		// Looks like a variable name
		if (/^[a-zA-Z_][a-zA-Z0-9_]*$/.test(val)) return val;
		// Otherwise wrap in quotes
		return '"' + val + '"';
	}

	// ═══════════════════════════════════════════════════════════════════
	// BLOCK DEFINITIONS
	// ═══════════════════════════════════════════════════════════════════
	var BLOCK_DEFS = {
		get_top: {
			category: 'sensing', color: '#4fc3f7', label: '🎯',
			fields: [
				{ type: 'input', key: 'varname', default: 'oben', placeholder: 'Variablenname' }
			],
			labelAfter: '= was oben ist',
			toDSL: function(f) { return (f.varname || 'oben') + ' = topmost_detection'; }
		},
		get_bottom: {
			category: 'sensing', color: '#4fc3f7', label: '🎯',
			fields: [
				{ type: 'input', key: 'varname', default: 'unten', placeholder: 'Variablenname' }
			],
			labelAfter: '= was unten ist',
			toDSL: function(f) { return (f.varname || 'unten') + ' = bottommost_detection'; }
		},
		get_largest: {
			category: 'sensing', color: '#4fc3f7', label: '🎯',
			fields: [
				{ type: 'input', key: 'varname', default: 'groesstes', placeholder: 'Variablenname' }
			],
			labelAfter: '= größte Erkennung',
			toDSL: function(f) { return (f.varname || 'groesstes') + ' = largest_detection'; }
		},
		get_smallest: {
			category: 'sensing', color: '#4fc3f7', label: '🎯',
			fields: [
				{ type: 'input', key: 'varname', default: 'kleinstes', placeholder: 'Variablenname' }
			],
			labelAfter: '= kleinste Erkennung',
			toDSL: function(f) { return (f.varname || 'kleinstes') + ' = smallest_detection'; }
		},
		get_best: {
			category: 'sensing', color: '#4fc3f7', label: '🎯',
			fields: [
				{ type: 'input', key: 'varname', default: 'bestes', placeholder: 'Variablenname' }
			],
			labelAfter: '= sicherste Erkennung',
			toDSL: function(f) { return (f.varname || 'bestes') + ' = highest_conf_detection'; }
		},
		get_left: {
			category: 'sensing', color: '#4fc3f7', label: '🎯',
			fields: [
				{ type: 'input', key: 'varname', default: 'left', placeholder: 'variable name' }
			],
			labelAfter: '= leftmost detection',
			toDSL: function(f) { return (f.varname || 'left') + ' = leftmost_detection'; }
		},
		get_right: {
			category: 'sensing', color: '#4fc3f7', label: '🎯',
			fields: [
				{ type: 'input', key: 'varname', default: 'right', placeholder: 'variable name' }
			],
			labelAfter: '= rightmost detection',
			toDSL: function(f) { return (f.varname || 'right') + ' = rightmost_detection'; }
		},
		get_count: {
			category: 'sensing', color: '#4fc3f7', label: '🔢',
			fields: [
				{ type: 'input', key: 'varname', default: 'count', placeholder: 'variable name' }
			],
			labelAfter: '= detection count',
			toDSL: function(f) { return (f.varname || 'count') + ' = detection_count'; }
		},
		'if': {
			category: 'control', color: '#ffb74d', label: '🔶 if',
			fields: [
				{ type: 'label_or_var', key: 'left_val', default: 'left', placeholder: 'variable' },
				{ type: 'select', key: 'operator', options: ['==','!=','>','<','>=','<='], default: '==' },
				{ type: 'label_or_input', key: 'right_val', default: '', placeholder: 'value' }
			],
			labelAfter: 'then',
			canHaveSecondCondition: true,
			toDSL: function(f) {
				var c = (f.left_val||'left') + ' ' + (f.operator||'==') + ' ' + formatValueForDSL(f.right_val);
				if (f.logic && f.left_val2) {
					c += ' ' + f.logic + ' ' + (f.left_val2||'right') + ' ' + (f.operator2||'==') + ' ' + formatValueForDSL(f.right_val2);
				}
				return 'if ' + c;
			}
		},
		'elif': {
			category: 'control', color: '#ffa726', label: '🔷 else if',
			fields: [
				{ type: 'label_or_var', key: 'left_val', default: 'left', placeholder: 'variable' },
				{ type: 'select', key: 'operator', options: ['==','!=','>','<','>=','<='], default: '==' },
				{ type: 'label_or_input', key: 'right_val', default: '', placeholder: 'value' }
			],
			labelAfter: 'then',
			canHaveSecondCondition: true,
			toDSL: function(f) {
				var c = (f.left_val||'left') + ' ' + (f.operator||'==') + ' ' + formatValueForDSL(f.right_val);
				if (f.logic && f.left_val2) {
					c += ' ' + f.logic + ' ' + (f.left_val2||'right') + ' ' + (f.operator2||'==') + ' ' + formatValueForDSL(f.right_val2);
				}
				return 'elif ' + c;
			}
		},
		'else': {
			category: 'control', color: '#78909c', label: '⬜ else',
			fields: [],
			toDSL: function() { return 'else'; }
		},
		'end': {
			category: 'control', color: '#90a4ae', label: '🔚 end',
			fields: [],
			toDSL: function() { return 'end'; }
		},
		'print': {
			category: 'output', color: '#ba68c8', label: '💬 print',
			fields: [
				{ type: 'input', key: 'message', default: '"Hello!"', placeholder: '"message" or variable', wide: true }
			],
			toDSL: function(f) { return 'print ' + (f.message || '""'); }
		},
		'set_var': {
			category: 'variables', color: '#e57373', label: '📦 set',
			fields: [
				{ type: 'input', key: 'varname', default: 'x', placeholder: 'name' }
			],
			labelMid: '=',
			fields2: [
				{ type: 'label_or_input', key: 'value', default: '', placeholder: 'value', wide: true }
			],
			toDSL: function(f) {
				return (f.varname || 'x') + ' = ' + formatValueForDSL(f.value);
			}
		}
	};

	// ═══════════════════════════════════════════════════════════════════
	// STATE
	// ═══════════════════════════════════════════════════════════════════
	var workspaceBlocks = [];
	var blockIdCounter = 0;
	var draggedBlockType = null;
	var draggedWorkspaceIdx = null;

	var workspace   = document.getElementById('block_workspace');
	var placeholder = document.getElementById('workspace_placeholder');
	var trashZone   = document.getElementById('trash_zone');
	var dslEditor   = document.getElementById('dsl_editor');

	function newBlockId() { return 'block_' + (++blockIdCounter); }

	// ═══════════════════════════════════════════════════════════════════
	// COLLECT VARIABLE NAMES from workspace (for variable dropdowns)
	// ═══════════════════════════════════════════════════════════════════
	function collectVariableNames() {
		var names = [], seen = {};
		for (var i = 0; i < workspaceBlocks.length; i++) {
			var b = workspaceBlocks[i], vn = null;
			if (['get_left','get_right','get_count','get_top','get_bottom',
				'get_largest','get_smallest','get_best'].indexOf(b.type) !== -1) {
				vn = b.fields.varname;
			} else if (b.type === 'set_var') {
				vn = b.fields.varname;
			}
			if (vn && !seen[vn]) { seen[vn] = true; names.push(vn); }
		}
		['links','rechts','oben','unten','anzahl','groesstes','kleinstes','bestes'].forEach(function(d) {
			if (!seen[d]) names.push(d);
		});
		return names;
	}


	// ═══════════════════════════════════════════════════════════════════
	// FETCH MODEL LABELS when model selection changes
	// ═══════════════════════════════════════════════════════════════════
	function fetchModelLabels(modelUuid) {
		if (!modelUuid || modelUuid === 'none') {
			modelLabels = [];
			updateLabelUI();
			return;
		}
		fetch('labels.php?model_uuid=' + encodeURIComponent(modelUuid))
			.then(function(r) {
				if (!r.ok) throw new Error('Failed');
				return r.json();
			})
			.then(function(labels) {
				modelLabels = Array.isArray(labels) ? labels : [];
				updateLabelUI();
			})
			.catch(function() {
				modelLabels = [];
				updateLabelUI();
			});
	}

	// ═══════════════════════════════════════════════════════════════════
	// UPDATE LABEL UI (info bar + palette label blocks)
	// ═══════════════════════════════════════════════════════════════════
	function updateLabelUI() {
		// Info bar
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

		// Palette label blocks
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

		// Re-render so dropdowns update
		renderWorkspace();
	}

	// Listen for model changes
	var modelSelect = document.getElementById('game_model_select');
	if (modelSelect) {
		modelSelect.addEventListener('change', function() { fetchModelLabels(this.value); });
		if (modelSelect.value && modelSelect.value !== 'none') {
			fetchModelLabels(modelSelect.value);
		}
	}

	// ═══════════════════════════════════════════════════════════════════
	// RENDER WORKSPACE
	// ═══════════════════════════════════════════════════════════════════
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
	}

	// ═══════════════════════════════════════════════════════════════════
	// CREATE BLOCK ELEMENT
	// ═══════════════════════════════════════════════════════════════════
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

		// Label after
		if (def.labelAfter) {
			var s = document.createElement('span');
			s.textContent = ' ' + def.labelAfter;
			el.appendChild(s);
		}

		// Mid label
		if (def.labelMid) {
			var m = document.createElement('span');
			m.textContent = ' ' + def.labelMid + ' ';
			el.appendChild(m);
		}

		// Second fields
		if (def.fields2) {
			def.fields2.forEach(function(fd) { el.appendChild(createFieldElement(block, fd)); });
		}

		// ── Second condition toggle for if/elif ──
		if (def.canHaveSecondCondition) {
			var addBtn = document.createElement('button');
			addBtn.textContent = block.fields.logic ? '➖' : '➕';
			addBtn.style.cssText = 'background:rgba(0,0,0,0.2);border:1px solid rgba(255,255,255,0.3);border-radius:50%;width:22px;height:22px;color:#fff;cursor:pointer;font-size:11px;margin-left:4px;padding:0;';
			addBtn.title = block.fields.logic ? 'Remove second condition' : 'Add second condition';
			addBtn.addEventListener('click', function(e) {
				e.stopPropagation();
				if (block.fields.logic) {
					delete block.fields.logic;
					delete block.fields.left_val2;
					delete block.fields.operator2;
					delete block.fields.right_val2;
				} else {
					block.fields.logic = 'and';
					block.fields.left_val2 = 'right';
					block.fields.operator2 = '==';
					block.fields.right_val2 = modelLabels.length > 0 ? modelLabels[0] : '';
				}
				renderWorkspace();
			});
			el.appendChild(addBtn);

			// Render second condition
			if (block.fields.logic) {
				var logicSel = document.createElement('select');
				logicSel.className = 'block-select';
				logicSel.style.marginLeft = '4px';
				['and', 'or'].forEach(function(opt) {
					var o = document.createElement('option');
					o.value = opt; o.textContent = opt;
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
					{ type: 'label_or_var', key: 'left_val2', default: 'right', placeholder: 'variable' },
					{ type: 'select', key: 'operator2', options: ['==','!=','>','<','>=','<='], default: '==' },
					{ type: 'label_or_input', key: 'right_val2', default: modelLabels.length > 0 ? modelLabels[0] : '', placeholder: 'value' }
				];
				c2.forEach(function(fd) { el.appendChild(createFieldElement(block, fd)); });
			}
		}

		// ── Drag events for reordering ──
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

	// ═══════════════════════════════════════════════════════════════════
	// CREATE FIELD ELEMENT
	// ═══════════════════════════════════════════════════════════════════
	function createFieldElement(block, fieldDef) {

		// ── label_or_input: dropdown of model labels + custom ──
		if (fieldDef.type === 'label_or_input') {
			if (modelLabels.length > 0) {
				var wrapper = document.createElement('span');
				wrapper.style.cssText = 'display:inline-flex;align-items:center;gap:2px;';

				var cur = block.fields[fieldDef.key] !== undefined
					? block.fields[fieldDef.key] : (fieldDef.default || '');
				if (block.fields[fieldDef.key] === undefined)
					block.fields[fieldDef.key] = fieldDef.default || '';

				var isKnown = (modelLabels.indexOf(cur) !== -1) || cur === 'none' || cur === '';
				var isCustom = !isKnown && cur !== '';

				var sel = document.createElement('select');
				sel.className = 'block-select';

				// "none" option
				var noneOpt = document.createElement('option');
				noneOpt.value = 'none'; noneOpt.textContent = '— none —';
				if (cur === '' || cur === 'none') noneOpt.selected = true;
				sel.appendChild(noneOpt);

				// Model labels
				modelLabels.forEach(function(label) {
					var o = document.createElement('option');
					o.value = label; o.textContent = '🏷️ ' + label;
					if (cur === label) o.selected = true;
					sel.appendChild(o);
				});

				// Custom option
				var custOpt = document.createElement('option');
				custOpt.value = '__custom__'; custOpt.textContent = '✏️ custom...';
				if (isCustom) custOpt.selected = true;
				sel.appendChild(custOpt);

				// Custom input
				var custIn = document.createElement('input');
				custIn.type = 'text';
				custIn.className = 'block-input';
				custIn.placeholder = 'type value...';
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
			// Fallback: no labels loaded
			return createPlainInput(block, fieldDef);
		}

		// ── label_or_var: dropdown of variable names + custom ──
		if (fieldDef.type === 'label_or_var') {
			var wrapper = document.createElement('span');
			wrapper.style.cssText = 'display:inline-flex;align-items:center;gap:2px;';

			var cur = block.fields[fieldDef.key] !== undefined
				? block.fields[fieldDef.key] : (fieldDef.default || '');
			if (block.fields[fieldDef.key] === undefined)
				block.fields[fieldDef.key] = fieldDef.default || '';

			var varNames = collectVariableNames();
			var isKnown = varNames.indexOf(cur) !== -1;
			var isCustom = !isKnown && cur !== '';

			var sel = document.createElement('select');
			sel.className = 'block-select';

			if (varNames.length === 0) {
				var emptyOpt = document.createElement('option');
				emptyOpt.value = ''; emptyOpt.textContent = '(no variables)';
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
			custOpt.value = '__custom__'; custOpt.textContent = '✏️ custom...';
			if (isCustom) custOpt.selected = true;
			sel.appendChild(custOpt);

			var custIn = document.createElement('input');
			custIn.type = 'text';
			custIn.className = 'block-input';
			custIn.placeholder = 'var name...';
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

		// ── Standard input ──
		if (fieldDef.type === 'input' || fieldDef.type === 'condition') {
			return createPlainInput(block, fieldDef);
		}

		// ── Standard select ──
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

		// Fallback
		var span = document.createElement('span');
		span.textContent = fieldDef.default || '';
		return span;
	}

	// ═══════════════════════════════════════════════════════════════════
	// PLAIN INPUT HELPER
	// ═══════════════════════════════════════════════════════════════════
	function createPlainInput(block, fieldDef) {
		var input = document.createElement('input');
		input.type = 'text';
		input.className = 'block-input';
		input.placeholder = fieldDef.placeholder || '';
		input.value = block.fields[fieldDef.key] !== undefined
			? block.fields[fieldDef.key] : (fieldDef.default || '');
		if (fieldDef.wide) input.style.minWidth = '140px';
		if (block.fields[fieldDef.key] === undefined)
			block.fields[fieldDef.key] = fieldDef.default || '';

		input.addEventListener('input', function() {
			block.fields[fieldDef.key] = this.value;
			generateDSL();
		});
		input.addEventListener('mousedown', function(e) { e.stopPropagation(); });
		input.addEventListener('dragstart', function(e) { e.preventDefault(); e.stopPropagation(); });
		return input;
	}

	// ═══════════════════════════════════════════════════════════════════
	// HANDLE DROP (from palette or reorder)
	// ═══════════════════════════════════════════════════════════════════
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

	// ═══════════════════════════════════════════════════════════════════
	// CREATE NEW BLOCK INSTANCE
	// ═══════════════════════════════════════════════════════════════════
	function createNewBlock(type) {
		// Handle dynamic label_value blocks (dragged from palette labels)
		if (type && type.startsWith('label_value:')) {
			var labelName = type.substring('label_value:'.length);
			return {
				id: newBlockId(),
				type: 'set_var',
				fields: {
					varname: 'label',
					value: labelName
				}
			};
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

	// ═══════════════════════════════════════════════════════════════════
	// GENERATE DSL CODE FROM BLOCKS
	// ═══════════════════════════════════════════════════════════════════
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

	// ═══════════════════════════════════════════════════════════════════
	// PALETTE DRAG EVENTS (static blocks)
	// ═══════════════════════════════════════════════════════════════════
	function bindPaletteDragEvents() {
		var paletteBlocks = document.querySelectorAll('#block_palette .palette-block');
		paletteBlocks.forEach(function(paletteEl) {
			// Skip dynamically created label blocks (they have their own handlers)
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

	// ═══════════════════════════════════════════════════════════════════
	// WORKSPACE DRAG/DROP EVENTS
	// ═══════════════════════════════════════════════════════════════════
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

	// ═══════════════════════════════════════════════════════════════════
	// TRASH ZONE
	// ═══════════════════════════════════════════════════════════════════
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

	// ═══════════════════════════════════════════════════════════════════
	// SHOW CODE BUTTON
	// ═══════════════════════════════════════════════════════════════════
	var showCodeBtn = document.getElementById('btn_show_code');
	if (showCodeBtn) {
		showCodeBtn.addEventListener('click', function() {
			var code = generateDSL();
			document.getElementById('code_preview_content').textContent = code || '(no blocks yet)';
			document.getElementById('code_preview_modal').classList.add('visible');
		});
	}

	// ═══════════════════════════════════════════════════════════════════
	// CLOSE MODAL ON BACKGROUND CLICK
	// ═══════════════════════════════════════════════════════════════════
	var modal = document.getElementById('code_preview_modal');
	if (modal) {
		modal.addEventListener('click', function(e) {
			if (e.target === modal) {
				modal.classList.remove('visible');
			}
		});
	}

	// ═══════════════════════════════════════════════════════════════════
	// INITIALIZE — empty workspace, user builds from scratch
	// ═══════════════════════════════════════════════════════════════════
	workspaceBlocks = [];
	renderWorkspace();

})();
