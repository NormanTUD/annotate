<?php
	$GLOBALS["no_home"] = 1;
	include("header.php");
	include_once("functions.php");
	$available_models = get_list_of_models();
?>

<link rel="stylesheet" href="game_editor.css">

<div id="game_editor_page">

<h1>✊✌️✋ Stein Schere Papier – KI-Spiel Baukasten</h1>
<p class="subtitle">Trainiere dein KI-Modell und programmiere dein eigenes Spiel – ganz ohne Vorkenntnisse!</p>

<!-- Compact top bar: model + camera + confidence in one row -->
<div class="topbar-controls">
	<div class="topbar-item">
		<label>🤖 Modell:</label>
		<select id="game_model_select">
			<option value="none">— Kein Modell —</option>
			<?php foreach ($available_models as $_model): ?>
				<option value="<?php echo htmlspecialchars($_model[1]); ?>">
					<?php echo htmlspecialchars($_model[0]); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</div>
	<div class="topbar-item">
		<label>📷</label>
		<select id="game_camera_select">
			<option value="">Kameras werden geladen...</option>
		</select>
	</div>
	<div class="topbar-item">
		<label>🎚️</label>
		<input type="range" id="game_conf_slider" min="0" max="1" step="0.01" value="0.3">
		<span id="game_conf_value">0.30</span>
	</div>
	<div class="topbar-item" style="display:none;">
		<input type="number" id="game_fps" min="1" max="10" value="3">
	</div>
	<div class="topbar-item" id="model_labels_info" style="display:none;">
		<span id="model_labels_chips"></span>
	</div>
</div>

<!-- Main layout: everything visible at once -->
<div id="game_editor_container" class="always-visible">

	<!-- Left: Block Editor -->
	<div id="editor_panel">
		<div class="panel-header">
			<h3>🧩 Dein Programm</h3>
			<div class="editor-actions">
				<button id="btn_load_example" title="Beispiel laden">💡</button>
				<button id="btn_show_code" title="Code anzeigen">👁</button>
				<button id="btn_clear_output" title="Ausgabe löschen">🗑</button>
			</div>
		</div>
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
						<span class="block-hint">Zählt wie viele Objekte sichtbar sind</span>
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
			</div>

			<!-- Workspace -->
			<div id="block_workspace">
				<div id="workspace_placeholder">
					<span class="big-arrow">⬅️</span>
					Ziehe Blöcke von links hierher!<br><br>
					<span style="font-size: 12px; color: #585b70;">
						💡 Klicke auf 💡 oben für einen Schnellstart!
					</span>
				</div>
				<div id="trash_zone">🗑️</div>
			</div>
		</div>
	</div>

	<!-- Right: Camera + Output (always visible) -->
	<div id="preview_panel">
		<div class="preview-card cam-card">
			<div id="game_webcam_container">
				<video id="game_video" autoplay playsinline muted></video>
				<canvas id="game_overlay_canvas"></canvas>
				<div id="game_text_overlay"></div>
				<div id="cam_placeholder">
					<span>📷</span>
					<p>Wähle ein Modell um die Kamera zu starten</p>
				</div>
			</div>
		</div>

		<div class="preview-card output-card">
			<h3>📋 Protokoll</h3>
			<div id="game_output">Wähle ein Modell oben – die Kamera startet automatisch! 🚀
</div>
		</div>

		<div id="game_status">Status: Wähle ein Modell zum Starten</div>
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
