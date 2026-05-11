<?php
	include("header.php");
	include_once("functions.php");
	$available_models = get_list_of_models();
?>

<style>
	/* ─── Global Toy Look ────────────────────────────────────── */
	#game_editor_page {
		font-family: 'Nunito', 'Comic Sans MS', 'Segoe UI', sans-serif;
		max-width: 1300px;
		margin: 0 auto;
		padding: 10px;
	}

	#game_editor_page h1 {
		text-align: center;
		font-size: 2em;
		margin-bottom: 4px;
		color: #fff;
	}

	#game_editor_page .subtitle {
		text-align: center;
		color: #a6adc8;
		font-size: 14px;
		margin-bottom: 16px;
	}

	/* ─── Step Wizard Bar ────────────────────────────────────── */
	.step-bar {
		display: flex;
		gap: 0;
		margin-bottom: 16px;
		border-radius: 12px;
		overflow: hidden;
		border: 2px solid #45475a;
	}

	.step-item {
		flex: 1;
		padding: 12px 16px;
		background: #1e1e2e;
		text-align: center;
		font-size: 13px;
		font-weight: 700;
		color: #6c7086;
		position: relative;
		transition: all 0.3s;
		cursor: pointer;
	}

	.step-item.active {
		background: #313244;
		color: #cdd6f4;
	}

	.step-item.done {
		background: #1e3a2e;
		color: #a6e3a1;
	}

	.step-item .step-num {
		display: inline-block;
		width: 24px;
		height: 24px;
		line-height: 24px;
		border-radius: 50%;
		background: #45475a;
		color: #cdd6f4;
		font-size: 12px;
		margin-right: 6px;
	}

	.step-item.active .step-num { background: #89b4fa; color: #1e1e2e; }
	.step-item.done .step-num { background: #a6e3a1; color: #1e1e2e; }

	/* ─── Layout ─────────────────────────────────────────────── */
	#game_editor_container {
		display: flex;
		gap: 16px;
		flex-wrap: wrap;
	}

	#editor_panel {
		flex: 2;
		min-width: 480px;
		display: flex;
		flex-direction: column;
	}

	#preview_panel {
		flex: 1;
		min-width: 320px;
		display: flex;
		flex-direction: column;
		gap: 12px;
	}

	/* ─── Quick Setup Card ───────────────────────────────────── */
	.setup-card {
		background: #1e1e2e;
		border: 2px solid #45475a;
		border-radius: 12px;
		padding: 16px;
		margin-bottom: 12px;
	}

	.setup-card h3 {
		margin: 0 0 12px 0;
		color: #f9e2af;
		font-size: 15px;
	}

	.setup-row {
		display: flex;
		align-items: center;
		gap: 12px;
		margin-bottom: 10px;
		flex-wrap: wrap;
	}

	.setup-row label {
		color: #cdd6f4;
		font-size: 13px;
		font-weight: 600;
		min-width: 80px;
	}

	.setup-row select,
	.setup-row input {
		background: #313244;
		color: #cdd6f4;
		border: 2px solid #45475a;
		border-radius: 8px;
		padding: 6px 12px;
		font-size: 13px;
		transition: border-color 0.2s;
	}

	.setup-row select:focus,
	.setup-row input:focus {
		border-color: #89b4fa;
		outline: none;
	}

	.help-bubble {
		background: #313244;
		border: 1px solid #585b70;
		border-radius: 8px;
		padding: 8px 12px;
		color: #bac2de;
		font-size: 12px;
		line-height: 1.4;
		margin-top: 8px;
	}

	.help-bubble .emoji-big {
		font-size: 18px;
		vertical-align: middle;
		margin-right: 4px;
	}

	/* ─── Block Palette (left sidebar) ───────────────────────── */
	#visual_editor_wrapper {
		display: flex;
		gap: 0;
		border: 2px solid #45475a;
		border-radius: 12px;
		overflow: hidden;
		min-height: 420px;
		background: #1e1e2e;
	}

	#block_palette {
		width: 220px;
		min-width: 220px;
		background: #181825;
		border-right: 2px solid #45475a;
		padding: 10px;
		overflow-y: auto;
		max-height: 480px;
	}

	.palette-category {
		margin-bottom: 14px;
	}

	.palette-category-title {
		font-size: 10px;
		font-weight: 700;
		text-transform: uppercase;
		letter-spacing: 1px;
		margin-bottom: 6px;
		padding: 3px 8px;
		border-radius: 4px;
		color: #1e1e2e;
	}

	.palette-block {
		padding: 7px 10px;
		margin: 3px 0;
		border-radius: 8px;
		font-size: 12px;
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

	.palette-block:active { cursor: grabbing; transform: scale(0.97); }

	.palette-block .block-hint {
		display: block;
		font-size: 9px;
		font-weight: 400;
		opacity: 0.8;
		margin-top: 2px;
	}

	/* Category colors */
	.cat-sensing { background: #4fc3f7; }
	.cat-control { background: #ffb74d; }
	.cat-output { background: #ba68c8; }
	.cat-variables { background: #e57373; }
	.cat-labels { background: #66bb6a; }
	.cat-display { background: #4dd0e1; }

	/* ─── Workspace (drop area) ──────────────────────────────── */
	#block_workspace {
		flex: 1;
		background: #2b2b3d;
		background-image: radial-gradient(circle, #3b3b5050 1px, transparent 1px);
		background-size: 24px 24px;
		padding: 16px;
		min-height: 420px;
		max-height: 480px;
		overflow-y: auto;
		position: relative;
	}

	#block_workspace.drag-over { background-color: #33335a; }

	#workspace_placeholder {
		color: #6c7086;
		font-size: 14px;
		text-align: center;
		padding-top: 60px;
		pointer-events: none;
		line-height: 1.8;
	}

	#workspace_placeholder .big-arrow {
		font-size: 40px;
		display: block;
		margin-bottom: 8px;
		animation: bounce 1.5s infinite;
	}

	@keyframes bounce {
		0%, 100% { transform: translateX(0); }
		50% { transform: translateX(-8px); }
	}

	/* ─── Placed Blocks ──────────────────────────────────────── */
	.workspace-block {
		padding: 9px 12px;
		margin: 5px 0;
		border-radius: 10px;
		font-size: 12px;
		font-weight: 600;
		color: #fff;
		text-shadow: 0 1px 2px rgba(0,0,0,0.3);
		box-shadow: 0 2px 6px rgba(0,0,0,0.25), inset 0 1px 0 rgba(255,255,255,0.12);
		cursor: grab;
		user-select: none;
		display: flex;
		align-items: center;
		gap: 6px;
		flex-wrap: wrap;
		position: relative;
		transition: opacity 0.15s;
	}

	.workspace-block:active { cursor: grabbing; }
	.workspace-block.dragging { opacity: 0.4; }

	.workspace-block .block-input {
		background: rgba(0,0,0,0.25);
		border: 1px solid rgba(255,255,255,0.2);
		border-radius: 14px;
		padding: 3px 10px;
		color: #fff;
		font-size: 11px;
		font-weight: 500;
		min-width: 55px;
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
		font-size: 11px;
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
		top: -5px;
		right: -5px;
		width: 18px;
		height: 18px;
		background: #f38ba8;
		border: 2px solid #1e1e2e;
		border-radius: 50%;
		color: #1e1e2e;
		font-size: 10px;
		font-weight: 900;
		cursor: pointer;
		display: none;
		align-items: center;
		justify-content: center;
		line-height: 1;
		z-index: 10;
	}

	.workspace-block:hover .block-delete { display: flex; }

	/* Indentation */
	.workspace-block.indent-1 { margin-left: 28px; }
	.workspace-block.indent-2 { margin-left: 56px; }
	.workspace-block.indent-3 { margin-left: 84px; }

	/* Drop indicator line */
	.drop-indicator {
		height: 4px;
		background: #89b4fa;
		border-radius: 2px;
		margin: 2px 0;
	}

	/* ─── Trash zone ─────────────────────────────────────────── */
	#trash_zone {
		position: absolute;
		bottom: 10px;
		right: 10px;
		width: 50px;
		height: 50px;
		background: rgba(243, 139, 168, 0.15);
		border: 2px dashed #f38ba8;
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 20px;
		opacity: 0;
		transition: opacity 0.2s, transform 0.2s;
		pointer-events: none;
		z-index: 100;
	}

	#trash_zone.visible { opacity: 1; pointer-events: auto; }
	#trash_zone.hover { background: rgba(243, 139, 168, 0.4); transform: scale(1.15); }

	/* ─── Hidden DSL textarea ────────────────────────────────── */
	#dsl_editor { display: none; }

	/* ─── Preview Panel ──────────────────────────────────────── */
	.preview-card {
		background: #1e1e2e;
		border: 2px solid #45475a;
		border-radius: 12px;
		padding: 12px;
	}

	.preview-card h3 {
		margin: 0 0 8px 0;
		font-size: 14px;
		color: #cdd6f4;
	}

	#game_webcam_container {
		position: relative;
		display: inline-block;
		width: 100%;
	}

	#game_video {
		width: 100%;
		border-radius: 8px;
		background: #11111b;
		display: block;
	}

	#game_overlay_canvas {
		position: absolute;
		top: 0;
		left: 0;
		width: 100%;
		height: 100%;
		pointer-events: none;
		border-radius: 8px;
	}

	#game_text_overlay {
		position: absolute;
		bottom: 0;
		left: 0;
		right: 0;
		padding: 12px 16px;
		background: rgba(0,0,0,0.75);
		border-radius: 0 0 8px 8px;
		color: #fff;
		font-size: 18px;
		font-weight: 700;
		text-align: center;
		min-height: 44px;
		display: flex;
		align-items: center;
		justify-content: center;
		transition: all 0.3s;
		pointer-events: none;
	}

	#game_text_overlay:empty {
		opacity: 0;
	}

	#game_text_overlay.winner {
		background: rgba(166, 227, 161, 0.85);
		color: #1e1e2e;
		font-size: 22px;
	}

	#game_text_overlay.loser {
		background: rgba(243, 139, 168, 0.85);
		color: #1e1e2e;
		font-size: 22px;
	}

	#game_text_overlay.draw {
		background: rgba(249, 226, 175, 0.85);
		color: #1e1e2e;
		font-size: 22px;
	}

	/* ─── Output Console ─────────────────────────────────────── */
	#game_output {
		width: 100%;
		height: 150px;
		background: #11111b;
		color: #a6e3a1;
		border: 2px solid #45475a;
		border-radius: 8px;
		padding: 10px;
		font-family: 'JetBrains Mono', monospace;
		font-size: 11px;
		line-height: 1.4;
		overflow-y: auto;
		white-space: pre-wrap;
	}

	/* ─── Action Buttons ─────────────────────────────────────── */
	.action-bar {
		display: flex;
		align-items: center;
		gap: 8px;
		flex-wrap: wrap;
		margin-bottom: 12px;
	}

	.action-bar button {
		padding: 10px 18px;
		border: none;
		border-radius: 10px;
		cursor: pointer;
		font-weight: 700;
		font-size: 14px;
		transition: background 0.2s, transform 0.1s;
	}

	.action-bar button:hover { transform: translateY(-1px); }
	.action-bar button:active { transform: scale(0.97); }
	.action-bar button:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

	#btn_run_game { background: #a6e3a1; color: #1e1e2e; font-size: 16px; }
	#btn_run_game.running { background: #f9e2af; color: #1e1e2e; }
	#btn_stop_game { background: #f38ba8; color: #1e1e2e; }
	#btn_clear_output { background: #585b70; color: #cdd6f4; font-size: 12px; padding: 8px 12px; }
	#btn_show_code { background: #cba6f7; color: #1e1e2e; font-size: 12px; padding: 8px 12px; }
	#btn_load_example { background: #89b4fa; color: #1e1e2e; font-size: 12px; padding: 8px 12px; }

	/* ─── Status bar ─────────────────────────────────────────── */
	#game_status {
		padding: 6px 12px;
		background: #181825;
		border: 1px solid #45475a;
		border-radius: 6px;
		color: #6c7086;
		font-size: 11px;
		font-family: monospace;
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

	#code_preview_modal.visible { display: flex; }

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

	#code_preview_box h3 { color: #cba6f7; margin: 0 0 12px 0; }

	#code_preview_box pre {
		background: #11111b;
		color: #cdd6f4;
		padding: 16px;
		border-radius: 8px;
		font-size: 12px;
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
		margin: 0 0 12px 0;
		padding: 8px 14px;
		background: #1e3a2e;
		border: 1px solid #66bb6a;
		border-radius: 8px;
		color: #a6e3a1;
		font-size: 12px;
		display: none;
	}

	#model_labels_info.visible { display: block; }

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

	/* ─── Responsive ─────────────────────────────────────────── */
	@media (max-width: 900px) {
		#game_editor_container { flex-direction: column; }
		#editor_panel { min-width: 100%; }
		#preview_panel { min-width: 100%; }
		#block_palette { width: 180px; min-width: 180px; }
	}
</style>

<div id="game_editor_page">

<h1>✊✌️✋ Stein Schere Papier – KI-Spiel Baukasten</h1>
<p class="subtitle">Trainiere dein KI-Modell und programmiere dein eigenes Spiel – ganz ohne Vorkenntnisse!</p>

<!-- Step Wizard -->
<div class="step-bar">
	<div class="step-item active" id="step1_indicator">
		<span class="step-num">1</span> Modell wählen
	</div>
	<div class="step-item" id="step2_indicator">
		<span class="step-num">2</span> Programm bauen
	</div>
	<div class="step-item" id="step3_indicator">
		<span class="step-num">3</span> Spielen! 🎉
	</div>
</div>

<!-- Setup Card -->
<div class="setup-card">
	<h3>⚡ Schnell-Setup</h3>
	<div class="setup-row">
		<label>🤖 KI-Modell:</label>
		<select id="game_model_select">
			<option value="none">— Bitte wählen —</option>
			<?php foreach ($available_models as $_model): ?>
				<option value="<?php echo htmlspecialchars($_model[1]); ?>">
					<?php echo htmlspecialchars($_model[0]); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</div>
	<div class="setup-row">
		<label>📷 Kamera:</label>
		<select id="game_camera_select">
			<option value="">Kameras werden geladen...</option>
		</select>
	</div>
	<div class="setup-row">
		<label>🎚️ Sicherheit:</label>
		<input type="range" id="game_conf_slider" min="0" max="1" step="0.01" value="0.3">
		<span id="game_conf_value" style="color:#cdd6f4; font-size:13px; min-width:35px;">0.30</span>
		<span style="color:#6c7086; font-size:11px;">(höher = strenger)</span>
	</div>
	<div class="setup-row" style="display:none;">
		<label>⏱️ FPS:</label>
		<input type="number" id="game_fps" min="1" max="10" value="2" style="width: 50px;">
	</div>

	<div class="help-bubble" id="setup_help">
		<span class="emoji-big">💡</span>
		<strong>Tipp:</strong> Wähle zuerst dein trainiertes Modell aus. Dann siehst du die Kategorien (z.B. Stein, Schere, Papier) und kannst dein Spiel programmieren!
	</div>
</div>

<div id="model_labels_info">
	<strong>🏷️ Dein Modell erkennt:</strong> <span id="model_labels_chips"></span>
</div>

<!-- Action Bar -->
<div class="action-bar">
	<button id="btn_run_game">▶️ Spiel starten!</button>
	<button id="btn_stop_game">⏹ Stopp</button>
	<button id="btn_load_example">💡 Beispiel laden</button>
	<button id="btn_show_code">👁 Code anzeigen</button>
	<button id="btn_clear_output">🗑 Ausgabe löschen</button>
</div>

<div id="game_editor_container">
	<!-- Editor Panel -->
	<div id="editor_panel">
		<h3 style="color: #cdd6f4; margin: 0 0 8px 0; font-size: 14px;">
			🧩 Ziehe Blöcke hierher um dein Spiel zu bauen
		</h3>
		<div id="visual_editor_wrapper">
			<!-- Palette -->
			<div id="block_palette">
				<div class="palette-category">
					<div class="palette-category-title" style="background:#4fc3f7;">📡 ERKENNUNG</div>
					<div class="palette-block cat-sensing" data-block-type="get_left" draggable="true">
						🎯 links = was links ist
						<span class="block-hint">Speichert was die Kamera links sieht</span>
					</div>
					<div class="palette-block cat-sensing" data-block-type="get_right" draggable="true">
						🎯 rechts = was rechts ist
						<span class="block-hint">Speichert was die Kamera rechts sieht</span>
					</div>
					<div class="palette-block cat-sensing" data-block-type="get_count" draggable="true">
						🔢 anzahl = wie viele?
						<span class="block-hint">Zählt wie viele Hände sichtbar sind</span>
					</div>
				</div>

				<div class="palette-category">
					<div class="palette-category-title" style="background:#ffb74d;">🔀 ENTSCHEIDUNG</div>
					<div class="palette-block cat-control" data-block-type="if" draggable="true">
						🔶 wenn ___ dann
						<span class="block-hint">Prüft eine Bedingung</span>
					</div>
					<div class="palette-block cat-control" data-block-type="elif" draggable="true">
						🔷 sonst wenn ___ dann
						<span class="block-hint">Weitere Bedingung prüfen</span>
					</div>
					<div class="palette-block cat-control" data-block-type="else" draggable="true">
						⬜ sonst
						<span class="block-hint">Wenn nichts anderes zutrifft</span>
					</div>
					<div class="palette-block cat-control" data-block-type="end" draggable="true">
						🔚 ende
						<span class="block-hint">Schließt einen wenn-Block</span>
					</div>
				</div>

				<div class="palette-category">
					<div class="palette-category-title" style="background:#ba68c8;">💬 AUSGABE</div>
					<div class="palette-block cat-output" data-block-type="print" draggable="true">
						💬 sag ___
						<span class="block-hint">Zeigt Text im Konsolen-Fenster</span>
					</div>
					<div class="palette-block cat-display" data-block-type="show_text" draggable="true">
						📺 zeige auf Bild ___
						<span class="block-hint">Schreibt Text direkt aufs Kamerabild!</span>
					</div>
				</div>

				<div class="palette-category">
					<div class="palette-category-title" style="background:#e57373;">📦 VARIABLEN</div>
					<div class="palette-block cat-variables" data-block-type="set_var" draggable="true">
						📦 setze ___ = ___
						<span class="block-hint">Speichert einen Wert</span>
					</div>
				</div>

				<!-- Dynamic labels category -->
				<div class="palette-category" id="palette_labels_category" style="display:none;">
					<div class="palette-category-title" style="background:#66bb6a;">🏷️ KATEGORIEN</div>
					<div id="palette_labels_container"></div>
				</div>

				<div class="palette-block cat-sensing" data-block-type="get_top" draggable="true">
				    🎯 oben = was oben ist
				    <span class="block-hint">Speichert was die Kamera oben sieht</span>
				</div>
				<div class="palette-block cat-sensing" data-block-type="get_bottom" draggable="true">
				    🎯 unten = was unten ist
				    <span class="block-hint">Speichert was die Kamera unten sieht</span>
				</div>
				<div class="palette-block cat-sensing" data-block-type="get_largest" draggable="true">
				    🎯 größtes = größte Erkennung
				    <span class="block-hint">Das größte erkannte Objekt</span>
				</div>
				<div class="palette-block cat-sensing" data-block-type="get_smallest" draggable="true">
				    🎯 kleinstes = kleinste Erkennung
				    <span class="block-hint">Das kleinste erkannte Objekt</span>
				</div>
				<div class="palette-block cat-sensing" data-block-type="get_best" draggable="true">
				    🎯 bestes = sicherste Erkennung
				    <span class="block-hint">Die Erkennung mit höchster Sicherheit</span>
				</div>

			</div>

			<!-- Workspace -->
			<div id="block_workspace">
				<div id="workspace_placeholder">
					<span class="big-arrow">⬅️</span>
					Ziehe Blöcke von links hierher!<br><br>
					<span style="font-size: 12px; color: #585b70;">
						💡 Klicke auf "Beispiel laden" für einen Schnellstart!
					</span>
				</div>
				<div id="trash_zone">🗑️</div>
			</div>
		</div>
	</div>

	<!-- Preview Panel -->
	<div id="preview_panel">
		<div class="preview-card">
			<h3>📺 Kamera & Ergebnis</h3>
			<div id="game_webcam_container">
				<video id="game_video" autoplay playsinline muted></video>
				<canvas id="game_overlay_canvas"></canvas>
				<div id="game_text_overlay"></div>
			</div>
		</div>

		<div class="preview-card">
			<h3>📋 Protokoll</h3>
			<div id="game_output">Hier erscheint die Ausgabe deines Spiels...
</div>
		</div>

		<div id="game_status">Status: Bereit</div>
	</div>
</div>

<!-- Hidden textarea for interpreter -->
<textarea id="dsl_editor" style="display:none;"></textarea>

<!-- Code preview modal -->
<div id="code_preview_modal">
	<div id="code_preview_box">
		<h3>📝 Dein Programm-Code</h3>
		<pre id="code_preview_content"></pre>
		<button onclick="document.getElementById('code_preview_modal').classList.remove('visible');">Schließen ✕</button>
	</div>
</div>

</div> <!-- end #game_editor_page -->

<script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@4/dist/tf.min.js"></script>
<script>
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

	    var l1 = modelLabels.length > 0 ? modelLabels[0] : 'Stein';
	    var l2 = modelLabels.length > 1 ? modelLabels[1] : 'Schere';
	    var l3 = modelLabels.length > 2 ? modelLabels[2] : 'Papier';

	    // anzahl = detection_count
	    workspaceBlocks.push({ id: newBlockId(), type: 'get_count', fields: { varname: 'anzahl' } });
	    workspaceBlocks.push({ id: newBlockId(), type: 'get_left', fields: { varname: 'links' } });
	    workspaceBlocks.push({ id: newBlockId(), type: 'get_right', fields: { varname: 'rechts' } });

	    // if anzahl == 1 → nur anzeigen was erkannt wurde
	    workspaceBlocks.push({ id: newBlockId(), type: 'if', fields: { left_val: 'anzahl', operator: '==', right_val: '1' } });
	    workspaceBlocks.push({ id: newBlockId(), type: 'show_text', fields: { message: '"Erkannt: " + links + " – zeig noch eine Hand!"', style: 'normal' } });

	    // elif anzahl == 2 → Spiel auswerten
	    workspaceBlocks.push({ id: newBlockId(), type: 'elif', fields: { left_val: 'anzahl', operator: '==', right_val: '2' } });

	    // Nested: wer gewinnt?
	    workspaceBlocks.push({ id: newBlockId(), type: 'if', fields: {
		left_val: 'links', operator: '==', right_val: l1,
		logic: 'and', left_val2: 'rechts', operator2: '==', right_val2: l2
	    }});
	    workspaceBlocks.push({ id: newBlockId(), type: 'show_text', fields: { message: '"' + l1 + ' schlägt ' + l2 + ' – Links gewinnt! 🎉"', style: 'winner' } });

	    workspaceBlocks.push({ id: newBlockId(), type: 'elif', fields: {
		left_val: 'links', operator: '==', right_val: l2,
		logic: 'and', left_val2: 'rechts', operator2: '==', right_val2: l3
	    }});
	    workspaceBlocks.push({ id: newBlockId(), type: 'show_text', fields: { message: '"' + l2 + ' schlägt ' + l3 + ' – Links gewinnt! 🎉"', style: 'winner' } });

	    workspaceBlocks.push({ id: newBlockId(), type: 'elif', fields: {
		left_val: 'links', operator: '==', right_val: l3,
		logic: 'and', left_val2: 'rechts', operator2: '==', right_val2: l1
	    }});
	    workspaceBlocks.push({ id: newBlockId(), type: 'show_text', fields: { message: '"' + l3 + ' schlägt ' + l1 + ' – Links gewinnt! 🎉"', style: 'winner' } });

	    workspaceBlocks.push({ id: newBlockId(), type: 'elif', fields: {
		left_val: 'links', operator: '==', right_val: 'rechts'
	    }});
	    workspaceBlocks.push({ id: newBlockId(), type: 'show_text', fields: { message: '"Gleichstand! 🤝 Nochmal!"', style: 'draw' } });

	    workspaceBlocks.push({ id: newBlockId(), type: 'else', fields: {} });
	    workspaceBlocks.push({ id: newBlockId(), type: 'show_text', fields: { message: '"Rechts gewinnt! 💪"', style: 'loser' } });

	    workspaceBlocks.push({ id: newBlockId(), type: 'end', fields: {} }); // end inner if

	    // elif anzahl >= 3 → zu viele
	    workspaceBlocks.push({ id: newBlockId(), type: 'elif', fields: { left_val: 'anzahl', operator: '>=', right_val: '3' } });
	    workspaceBlocks.push({ id: newBlockId(), type: 'show_text', fields: { message: '"Zu viele Hände! Nur 2 zeigen 🙈"', style: 'draw' } });

	    // else → nichts erkannt
	    workspaceBlocks.push({ id: newBlockId(), type: 'else', fields: {} });
	    workspaceBlocks.push({ id: newBlockId(), type: 'show_text', fields: { message: '"Zeigt eure Hände! ✋✋"', style: 'normal' } });

	    // end outer if
	    workspaceBlocks.push({ id: newBlockId(), type: 'end', fields: {} });

	    renderWorkspace();
	}

	// ─── Initialize ─────────────────────────────────────────────────────
	workspaceBlocks = [];
	renderWorkspace();

})();
</script>

<script>
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
</script>

<?php
	include_once("footer.php");
?>
