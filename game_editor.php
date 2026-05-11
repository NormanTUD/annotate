<?php
	include("header.php");
	include_once("functions.php");
	$available_models = get_list_of_models();
?>

<link rel="stylesheet" href="game_editor.css">

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
<script src="visual_blocks.js"></script>
<script src="game_editor_engine.js"></script>

<?php
	include_once("footer.php");
?>
