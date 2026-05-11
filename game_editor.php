<?php
	include("header.php");
	include_once("functions.php");
	$available_models = get_list_of_models();
?>

<style>
	/* ─── Layout ─────────────────────────────────────────────── */
	#game_editor_container {
		display: flex;
		gap: 20px;
		margin: 10px 0;
		flex-wrap: wrap;
	}

	#editor_panel {
		flex: 2;
		min-width: 500px;
		display: flex;
		flex-direction: column;
	}

	#output_panel {
		flex: 1;
		min-width: 280px;
	}

	/* ─── Block Palette (left sidebar) ───────────────────────── */
	#visual_editor_wrapper {
		display: flex;
		gap: 0;
		border: 2px solid #45475a;
		border-radius: 12px;
		overflow: hidden;
		min-height: 480px;
		background: #1e1e2e;
	}

	#block_palette {
		width: 240px;
		min-width: 240px;
		background: #181825;
		border-right: 2px solid #45475a;
		padding: 12px;
		overflow-y: auto;
		max-height: 520px;
	}

	.palette-category {
		margin-bottom: 16px;
	}

	.palette-category-title {
		font-size: 11px;
		font-weight: 700;
		text-transform: uppercase;
		letter-spacing: 1px;
		margin-bottom: 8px;
		padding: 4px 8px;
		border-radius: 4px;
		color: #1e1e2e;
	}

	.palette-block {
		padding: 8px 12px;
		margin: 4px 0;
		border-radius: 8px;
		font-size: 13px;
		font-weight: 600;
		cursor: grab;
		user-select: none;
		color: #fff;
		text-shadow: 0 1px 2px rgba(0,0,0,0.3);
		box-shadow: 0 2px 4px rgba(0,0,0,0.2), inset 0 1px 0 rgba(255,255,255,0.15);
		transition: transform 0.1s, box-shadow 0.1s;
		position: relative;
	}

	.palette-block:hover {
		transform: translateY(-1px);
		box-shadow: 0 4px 8px rgba(0,0,0,0.3), inset 0 1px 0 rgba(255,255,255,0.15);
	}

	.palette-block:active {
		cursor: grabbing;
		transform: scale(0.97);
	}

	/* Category colors */
	.cat-sensing { background: #4fc3f7; }
	.cat-control { background: #ffb74d; }
	.cat-output { background: #ba68c8; }
	.cat-variables { background: #e57373; }
	.cat-labels { background: #66bb6a; }

	/* ─── Workspace (drop area) ──────────────────────────────── */
	#block_workspace {
		flex: 1;
		background: #2b2b3d;
		background-image:
			radial-gradient(circle, #3b3b5050 1px, transparent 1px);
		background-size: 24px 24px;
		padding: 20px;
		min-height: 480px;
		max-height: 520px;
		overflow-y: auto;
		position: relative;
	}

	#block_workspace.drag-over {
		background-color: #33335a;
	}

	#workspace_placeholder {
		color: #6c7086;
		font-size: 15px;
		text-align: center;
		padding-top: 180px;
		pointer-events: none;
	}

	/* ─── Placed Blocks ──────────────────────────────────────── */
	.workspace-block {
		padding: 10px 14px;
		margin: 6px 0;
		border-radius: 10px;
		font-size: 13px;
		font-weight: 600;
		color: #fff;
		text-shadow: 0 1px 2px rgba(0,0,0,0.3);
		box-shadow: 0 2px 6px rgba(0,0,0,0.25), inset 0 1px 0 rgba(255,255,255,0.12);
		cursor: grab;
		user-select: none;
		display: flex;
		align-items: center;
		gap: 8px;
		flex-wrap: wrap;
		position: relative;
		transition: opacity 0.15s;
	}

	.workspace-block:active {
		cursor: grabbing;
	}

	.workspace-block.dragging {
		opacity: 0.4;
	}

	.workspace-block .block-input {
		background: rgba(0,0,0,0.25);
		border: 1px solid rgba(255,255,255,0.2);
		border-radius: 14px;
		padding: 3px 10px;
		color: #fff;
		font-size: 12px;
		font-weight: 500;
		min-width: 60px;
		outline: none;
	}

	.workspace-block .block-input:focus {
		border-color: #fff;
		background: rgba(0,0,0,0.4);
	}

	.workspace-block .block-select {
		background: rgba(0,0,0,0.25);
		border: 1px solid rgba(255,255,255,0.2);
		border-radius: 14px;
		padding: 3px 10px;
		color: #fff;
		font-size: 12px;
		font-weight: 500;
		outline: none;
		cursor: pointer;
	}

	.workspace-block .block-select option {
		background: #333;
		color: #fff;
	}

	.workspace-block .block-delete {
		position: absolute;
		top: -6px;
		right: -6px;
		width: 20px;
		height: 20px;
		background: #f38ba8;
		border: 2px solid #1e1e2e;
		border-radius: 50%;
		color: #1e1e2e;
		font-size: 12px;
		font-weight: 900;
		cursor: pointer;
		display: none;
		align-items: center;
		justify-content: center;
		line-height: 1;
		z-index: 10;
	}

	.workspace-block:hover .block-delete {
		display: flex;
	}

	/* Indentation for blocks inside if/elif/else */
	.workspace-block.indent-1 { margin-left: 30px; }
	.workspace-block.indent-2 { margin-left: 60px; }
	.workspace-block.indent-3 { margin-left: 90px; }

	/* Drop indicator line */
	.drop-indicator {
		height: 4px;
		background: #89b4fa;
		border-radius: 2px;
		margin: 2px 0;
		transition: opacity 0.1s;
	}

	/* ─── Trash zone ─────────────────────────────────────────── */
	#trash_zone {
		position: absolute;
		bottom: 10px;
		right: 10px;
		width: 60px;
		height: 60px;
		background: rgba(243, 139, 168, 0.15);
		border: 2px dashed #f38ba8;
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 24px;
		opacity: 0;
		transition: opacity 0.2s, transform 0.2s;
		pointer-events: none;
		z-index: 100;
	}

	#trash_zone.visible {
		opacity: 1;
		pointer-events: auto;
	}

	#trash_zone.hover {
		background: rgba(243, 139, 168, 0.4);
		transform: scale(1.15);
	}

	/* ─── Hidden DSL textarea (for interpreter) ──────────────── */
	#dsl_editor {
		display: none;
	}

	/* ─── Output panel ───────────────────────────────────────── */
	#game_output {
		width: 100%;
		height: 400px;
		background: #11111b;
		color: #a6e3a1;
		border: 2px solid #45475a;
		border-radius: 8px;
		padding: 16px;
		font-family: 'JetBrains Mono', monospace;
		font-size: 13px;
		line-height: 1.5;
		overflow-y: auto;
		white-space: pre-wrap;
	}

	/* ─── Controls bar ───────────────────────────────────────── */
	.game-controls {
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

	.game-controls label {
		color: #cdd6f4;
		font-size: 13px;
	}

	.game-controls select,
	.game-controls input {
		background: #313244;
		color: #cdd6f4;
		border: 1px solid #45475a;
		border-radius: 4px;
		padding: 4px 8px;
		font-size: 13px;
	}

	.game-controls button {
		padding: 8px 16px;
		border: none;
		border-radius: 6px;
		cursor: pointer;
		font-weight: 600;
		font-size: 13px;
		transition: background 0.2s, opacity 0.2s;
	}

	.game-controls button:disabled {
		opacity: 0.5;
		cursor: not-allowed;
	}

	#btn_run_game { background: #a6e3a1; color: #1e1e2e; }
	#btn_run_game.running { background: #f9e2af; color: #1e1e2e; }
	#btn_stop_game { background: #f38ba8; color: #1e1e2e; }
	#btn_clear_output { background: #89b4fa; color: #1e1e2e; }
	#btn_show_code { background: #cba6f7; color: #1e1e2e; }

	#game_status {
		margin-top: 10px;
		padding: 8px 12px;
		background: #181825;
		border: 1px solid #45475a;
		border-radius: 6px;
		color: #cdd6f4;
		font-size: 12px;
		font-family: monospace;
		min-height: 30px;
	}

	/* ─── Code preview modal ─────────────────────────────────── */
	#code_preview_modal {
		display: none;
		position: fixed;
		top: 0; left: 0; right: 0; bottom: 0;
		background: rgba(0,0,0,0.6);
		z-index: 9999;
		align-items: center;
		justify-content: center;
	}

	#code_preview_modal.visible {
		display: flex;
	}

	#code_preview_box {
		background: #1e1e2e;
		border: 2px solid #cba6f7;
		border-radius: 12px;
		padding: 24px;
		max-width: 600px;
		width: 90%;
		max-height: 70vh;
		overflow-y: auto;
	}

	#code_preview_box h3 {
		color: #cba6f7;
		margin: 0 0 12px 0;
	}

	#code_preview_box pre {
		background: #11111b;
		color: #cdd6f4;
		padding: 16px;
		border-radius: 8px;
		font-size: 13px;
		overflow-x: auto;
		white-space: pre-wrap;
	}

	#code_preview_box button {
		margin-top: 12px;
		padding: 8px 20px;
		background: #cba6f7;
		color: #1e1e2e;
		border: none;
		border-radius: 6px;
		cursor: pointer;
		font-weight: 600;
	}

	/* ─── Label info bar ─────────────────────────────────────── */
	#model_labels_info {
		margin: 8px 0;
		padding: 8px 14px;
		background: #1e1e2e;
		border: 1px solid #45475a;
		border-radius: 8px;
		color: #a6adc8;
		font-size: 12px;
		display: none;
	}

	#model_labels_info.visible {
		display: block;
	}

	#model_labels_info .label-chip {
		display: inline-block;
		background: #66bb6a;
		color: #1e1e2e;
		padding: 2px 10px;
		border-radius: 12px;
		font-weight: 600;
		font-size: 11px;
		margin: 2px 4px 2px 0;
	}

	/* ─── Label info bar ─────────────────────────────────────── */
	#model_labels_info {
		margin: 8px 0;
		padding: 8px 14px;
		background: #1e1e2e;
		border: 1px solid #45475a;
		border-radius: 8px;
		color: #a6adc8;
		font-size: 12px;
		display: none;
	}

	#model_labels_info.visible {
		display: block;
	}

	#model_labels_info .label-chip {
		display: inline-block;
		background: #66bb6a;
		color: #1e1e2e;
		padding: 2px 10px;
		border-radius: 12px;
		font-weight: 600;
		font-size: 11px;
		margin: 2px 4px 2px 0;
	}

	.cat-labels { background: #66bb6a; }
</style>

<h1>🎮 Visual Game Editor</h1>

<div class="game-controls">
	<label>Model:
		<select id="game_model_select">
			<option value="none">None (no detections)</option>
			<?php foreach ($available_models as $_model): ?>
				<option value="<?php echo htmlspecialchars($_model[1]); ?>">
					<?php echo htmlspecialchars($_model[0]); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</label>

	<label>Camera:
		<select id="game_camera_select">
			<option value="">Loading cameras...</option>
		</select>
	</label>

	<label>Confidence:
		<input type="range" id="game_conf_slider" min="0" max="1" step="0.01" value="0.3">
		<span id="game_conf_value">0.30</span>
	</label>

	<label>Eval FPS:
		<input type="number" id="game_fps" min="1" max="10" value="2" style="width: 50px;">
	</label>

	<button id="btn_run_game">▶ Run Game</button>
	<button id="btn_stop_game">⏹ Stop</button>
	<button id="btn_clear_output">🗑 Clear</button>
	<button id="btn_show_code">👁 Show Code</button>
</div>

<div id="model_labels_info">
	<strong>🏷️ Model labels:</strong> <span id="model_labels_chips"></span>
</div>

<div id="model_labels_info">
	<strong>🏷️ Model labels:</strong> <span id="model_labels_chips"></span>
</div>

<div id="game_editor_container">
	<div id="editor_panel">
		<h3 style="color: #cdd6f4; margin: 0 0 8px 0;">🧩 Drag blocks to build your game</h3>
		<div id="visual_editor_wrapper">
			<!-- Palette -->
			<div id="block_palette">
				<div class="palette-category">
					<div class="palette-category-title" style="background:#4fc3f7;">📡 SENSING</div>
					<div class="palette-block cat-sensing" data-block-type="get_left" draggable="true">
						🎯 left = left detection
					</div>
					<div class="palette-block cat-sensing" data-block-type="get_right" draggable="true">
						🎯 right = right detection
					</div>
					<div class="palette-block cat-sensing" data-block-type="get_count" draggable="true">
						🔢 count = detection count
					</div>
				</div>

				<div class="palette-category">
					<div class="palette-category-title" style="background:#ffb74d;">🔀 CONTROL</div>
					<div class="palette-block cat-control" data-block-type="if" draggable="true">
						🔶 if ___ then
					</div>
					<div class="palette-block cat-control" data-block-type="elif" draggable="true">
						🔷 else if ___ then
					</div>
					<div class="palette-block cat-control" data-block-type="else" draggable="true">
						⬜ else
					</div>
					<div class="palette-block cat-control" data-block-type="end" draggable="true">
						🔚 end
					</div>
				</div>

				<div class="palette-category">
					<div class="palette-category-title" style="background:#ba68c8;">💬 OUTPUT</div>
					<div class="palette-block cat-output" data-block-type="print" draggable="true">
						💬 print ___
					</div>
				</div>

				<div class="palette-category">
					<div class="palette-category-title" style="background:#e57373;">📦 VARIABLES</div>
					<div class="palette-block cat-variables" data-block-type="set_var" draggable="true">
						📦 set ___ = ___
					</div>
				</div>

				<!-- Dynamic labels category - populated when model is selected -->
				<div class="palette-category" id="palette_labels_category" style="display:none;">
					<div class="palette-category-title" style="background:#66bb6a;">🏷️ MODEL LABELS</div>
					<div id="palette_labels_container"></div>
				</div>

				<!-- Dynamic labels category - populated when model is selected -->
				<div class="palette-category" id="palette_labels_category" style="display:none;">
					<div class="palette-category-title" style="background:#66bb6a;">🏷️ MODEL LABELS</div>
					<div id="palette_labels_container">
						<!-- Label blocks inserted here dynamically -->
					</div>
				</div>
			</div>

			<!-- Workspace -->
			<div id="block_workspace">
				<div id="workspace_placeholder">
					👆 Drag blocks here to build your game!<br><br>
					<span style="font-size: 12px; color: #585b70;">
						Tip: Select a model first to see its labels, then build conditions!
					</span>
				</div>
				<div id="trash_zone">🗑️</div>
			</div>
		</div>
	</div>

	<div id="output_panel">
		<h3 style="color: #cdd6f4; margin: 0 0 8px 0;">📺 Output</h3>
		<div id="game_output">Game output will appear here...
</div>
	</div>
</div>

<!-- Hidden textarea that the existing interpreter reads from -->
<textarea id="dsl_editor" style="display:none;"></textarea>

<div id="game_status">Status: Idle</div>

<div style="margin-top: 16px;">
	<div id="game_webcam_container" style="position: relative; display: inline-block;">
		<video id="game_video" autoplay playsinline muted width="640" style="max-width:100%; border:2px solid #45475a; border-radius:8px; background:#11111b;"></video>
		<canvas id="game_overlay_canvas" style="position:absolute; top:0; left:0; pointer-events:none; border-radius:8px;"></canvas>
	</div>
</div>

<!-- Code preview modal -->
<div id="code_preview_modal">
	<div id="code_preview_box">
		<h3>📝 Generated Code</h3>
		<pre id="code_preview_content"></pre>
		<button onclick="document.getElementById('code_preview_modal').classList.remove('visible');">Close</button>
	</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@4/dist/tf.min.js"></script>
<script src="visual_blocks.js"></script>
<script src="game_editor.js"></script>

<?php
	include_once("footer.php");
?>
