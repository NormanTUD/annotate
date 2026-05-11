<?php
	include("header.php");
	include_once("functions.php");
	$available_models = get_list_of_models();
?>

<style>
	#game_editor_container {
		display: flex;
		gap: 20px;
		margin: 10px 0;
		flex-wrap: wrap;
	}

	#editor_panel {
		flex: 1;
		min-width: 400px;
	}

	#output_panel {
		flex: 1;
		min-width: 300px;
	}

	#dsl_editor {
		width: 100%;
		height: 400px;
		background: #1e1e2e;
		color: #cdd6f4;
		border: 2px solid #45475a;
		border-radius: 8px;
		padding: 16px;
		font-family: 'JetBrains Mono', 'Fira Code', 'Consolas', monospace;
		font-size: 14px;
		line-height: 1.6;
		tab-size: 4;
		resize: vertical;
		outline: none;
		white-space: pre;
		overflow: auto;
	}

	#dsl_editor:focus {
		border-color: #cba6f7;
		box-shadow: 0 0 0 2px rgba(203, 166, 247, 0.2);
	}

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

	#btn_run_game {
		background: #a6e3a1;
		color: #1e1e2e;
	}

	#btn_run_game.running {
		background: #f9e2af;
		color: #1e1e2e;
	}

	#btn_stop_game {
		background: #f38ba8;
		color: #1e1e2e;
	}

	#btn_clear_output {
		background: #89b4fa;
		color: #1e1e2e;
	}

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

	.dsl-help {
		margin-top: 16px;
		padding: 12px 16px;
		background: #181825;
		border: 1px solid #45475a;
		border-radius: 8px;
		color: #bac2de;
		font-size: 12px;
	}

	.dsl-help h4 {
		color: #cba6f7;
		margin: 0 0 8px 0;
	}

	.dsl-help code {
		background: #313244;
		padding: 1px 5px;
		border-radius: 3px;
		color: #f9e2af;
		font-size: 12px;
	}

	.dsl-help pre {
		background: #1e1e2e;
		padding: 10px;
		border-radius: 6px;
		overflow-x: auto;
		color: #cdd6f4;
		font-size: 12px;
		margin: 8px 0;
	}
</style>

<h1>🎮 Game Logic Editor (DSL)</h1>

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
	<button id="btn_clear_output">🗑 Clear Output</button>
</div>

<div id="game_editor_container">
	<div id="editor_panel">
		<h3 style="color: #cdd6f4; margin: 0 0 8px 0;">📝 Game Script</h3>
		<textarea id="dsl_editor" spellcheck="false">
# Rock Paper Scissors - Webcam Game
# The webcam detects hand gestures on left and right sides

# Get detections from webcam
left = leftmost_detection
right = rightmost_detection

# Game logic
if left == "rock" and right == "scissors"
    print "Left wins! Rock beats Scissors"
elif left == "scissors" and right == "paper"
    print "Left wins! Scissors beats Paper"
elif left == "paper" and right == "rock"
    print "Left wins! Paper beats Rock"
elif right == "rock" and left == "scissors"
    print "Right wins! Rock beats Scissors"
elif right == "scissors" and left == "paper"
    print "Right wins! Scissors beats Paper"
elif right == "paper" and left == "rock"
    print "Right wins! Paper beats Rock"
elif left == right and left != "none"
    print "Draw! Both played " + left
else
    print "Waiting for two players..."
end
</textarea>
	</div>

	<div id="output_panel">
		<h3 style="color: #cdd6f4; margin: 0 0 8px 0;">📺 Output</h3>
		<div id="game_output">Game output will appear here...
</div>
	</div>
</div>

<div id="game_status">Status: Idle</div>

<div style="margin-top: 16px;">
	<div id="game_webcam_container" style="position: relative; display: inline-block;">
		<video id="game_video" autoplay playsinline muted width="640" style="max-width:100%; border:2px solid #45475a; border-radius:8px; background:#11111b;"></video>
		<canvas id="game_overlay_canvas" style="position:absolute; top:0; left:0; pointer-events:none; border-radius:8px;"></canvas>
	</div>
</div>

<div class="dsl-help">
	<h4>📖 DSL Reference</h4>
	<p>This is a simplified scripting language. <strong>Indentation/spaces don't cause errors</strong> — use them for readability only.</p>
	<ul style="margin: 8px 0; padding-left: 20px;">
		<li><code>leftmost_detection</code> — label of the leftmost detected object (or <code>"none"</code>)</li>
		<li><code>rightmost_detection</code> — label of the rightmost detected object (or <code>"none"</code>)</li>
		<li><code>leftmost_detection.probability</code> — confidence score (0.0 to 1.0)</li>
		<li><code>rightmost_detection.probability</code> — confidence score (0.0 to 1.0)</li>
		<li><code>detection_count</code> — number of current detections</li>
		<li><code>if</code> / <code>elif</code> / <code>else</code> / <code>end</code> — conditionals (use <code>end</code> to close blocks)</li>
		<li><code>and</code>, <code>or</code>, <code>not</code> — logical operators</li>
		<li><code>==</code>, <code>!=</code>, <code>&gt;</code>, <code>&lt;</code>, <code>&gt;=</code>, <code>&lt;=</code> — comparisons</li>
		<li><code>print "message"</code> or <code>print("message")</code> — output a line (parentheses optional)</li>
		<li><code>print "text" + variable</code> — concatenation</li>
		<li><code># comment</code> — comments (ignored)</li>
		<li>Variables: <code>x = "value"</code> or <code>x = leftmost_detection</code></li>
		<li><code>if</code> / <code>elif</code> / <code>else</code> / <code>end</code> — conditionals (use <code>end</code> or <code>}</code> to close blocks)</li>
		<li><code>{</code> and <code>}</code> — optional C-style block delimiters (can be mixed with <code>end</code>)</li>
	</ul>
	<pre># Example: Count detections
count = detection_count
if count >= 2
    print "Two or more objects detected!"
elif count == 1
    print "One object: " + leftmost_detection
else
    print "No objects detected"
end</pre>
</div>

<script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@4/dist/tf.min.js"></script>
<script src="game_editor.js"></script>

<?php
	include_once("footer.php");
?>
