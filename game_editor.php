<?php
    $GLOBALS["no_home"] = 1;
    include("header.php");
    include_once("functions.php");
    $available_models = get_list_of_models();
?>

<link rel="stylesheet" href="game_editor.css">

<div id="game_editor_page">

<h1>✊✌️✋ Stein Schere Papier – KI-Spiel Baukasten</h1>
<div id="game_output">🎮 Willkommen im KI-Spiel Baukasten!

👋 So geht's:
1️⃣ Wähle oben ein KI-Modell aus
2️⃣ Klicke auf "🎮 Beispiele" für fertige Spiele
3️⃣ Oder baue dein eigenes Programm mit den Blöcken links!

💡 Tipp: Starte mit einem Beispiel und ändere es dann ab!
</div>

<!-- Compact top bar -->
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

<!-- Main layout -->
<div id="game_editor_container" class="always-visible">

    <!-- Left: Block Editor -->
    <div id="editor_panel">
        <div class="panel-header">
            <h3>🧩 Dein Programm</h3>
            <div class="editor-actions">
		<!-- REPLACE the single 💡 button in .editor-actions with: -->
		<div class="editor-actions">
		    <button id="btn_show_examples" title="Beispiele">🎮 Beispiele</button>
		    <button id="btn_show_code" title="Code anzeigen">👁</button>
		</div>

		<!-- ADD this modal BEFORE the code_preview_modal div: -->
		<div id="example_gallery_modal">
		    <div id="example_gallery_box">
			<h2>🎮 Wähle ein Spiel!</h2>
			<p class="gallery-subtitle">Klicke auf ein Spiel, um es zu laden. Du kannst es danach verändern!</p>
			<div id="example_cards_container"></div>
			<button class="gallery-close" onclick="document.getElementById('example_gallery_modal').classList.remove('visible');">
			    Schließen ✕
			</button>
		    </div>
		</div>
                <button id="btn_show_code" title="Code anzeigen">👁</button>
                <button id="btn_clear_output" title="Ausgabe löschen">🗑</button>
            </div>
        </div>
        <div id="visual_editor_wrapper">
            <!-- Palette: generated entirely by JS now (compact tabs) -->
            <div id="block_palette"></div>

            <!-- Workspace -->
            <div id="block_workspace">
		<div id="workspace_placeholder">
		    <span class="big-arrow">🎮</span>
		    <strong>Hier baust du dein Programm!</strong><br><br>
		    <span style="font-size: 13px; color: #89b4fa;">
			⬅️ Ziehe Blöcke von links hierher<br>
			oder klicke oben auf <strong>🎮 Beispiele</strong>
		    </span>
		</div>
		</div>
        </div>
    </div>

    <!-- Right: Camera + Output -->
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

</div>

<script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@4/dist/tf.min.js"></script>
<script src="visual_blocks.js"></script>
<script src="game_editor_engine.js"></script>

<?php
    include_once("footer.php");
?>
