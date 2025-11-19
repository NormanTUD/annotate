<?php
include("header.php");
include_once("functions.php");

// Löschen eines Modells
if(get_get("delete_model")) {
	delete_model(get_get("delete_model"));
}

$available_models = get_list_of_models();
?>

<h1>Current models</h1>

<?php
if(count($available_models)) {
?>
    <table border=1>
	<tr>
	    <th>Name</th>
	    <th>UID</th>
	    <th>Delete?</th>
	</tr>
<?php
	for ($i = 0; $i < count($available_models); $i++) {
		print "<tr>\n";
		print " <td>".$available_models[$i][0]."</td>\n";
		print " <td>".$available_models[$i][1]."</td>\n";
		print " <td><a href='models.php?delete_model=".$available_models[$i][1]."'>Delete!</a></td>\n";
		print "</tr>\n";
	}
?>
    </table>
<?php
} else {
	echo "<p>No models available.</p>";
}
?>

<h2>Convert existing PyTorch model to TFJS</h2>

<p>Select a local .pt file and provide a model name. The server will convert it to TFJS automatically.</p>

<form enctype="multipart/form-data" method="POST" action="upload_model.php" id="model-upload-form">
    <input type="file" name="pt_model_file" accept=".pt" required>
    <input type="text" name="model_name" placeholder="Model Name" required>
    <input type="submit" value="Convert and Upload Model">
</form>

<div id="live-output-wrapper" style="display:none;">
<h2>Live Output</h2>
<p>The conversion process will take a while. If you run this locally, your computer may freeze for a brief period of time.</p>
<div id="model-output-container" style="border:1px solid #ccc; padding:10px; height:300px; overflow:auto; background:#f9f9f9; white-space:pre-wrap; font-family:monospace;background-color: black; color: green"></div>
<button id="reload-page-btn" style="display:none;">Reload Page</button>
</div>


<script>
document.addEventListener("DOMContentLoaded", function() {
    const wrapper = document.getElementById("live-output-wrapper");
    const container = document.getElementById("model-output-container");
    const reloadBtn = document.getElementById("reload-page-btn");

    // Funktion zum Anhängen von Text und automatischem Scrollen
    function appendOutput(text) {
        text = ansi_to_html(text);

        // HTML-safe breaks
        text = text.replace(/\n/g, "<br>");

        // leere Zeilen killen
        text = text.replace(/^\s*<br>\s*$/gm, "");

        container.innerHTML += text;
        container.scrollTop = container.scrollHeight;
    }

    function ansi_to_html(text) {
        // Entferne den [K]-Code komplett
        text = text.replace(/\x1b\[K/g, "");

        // Allgemeine ANSI-Sequenzen
        const ANSI_REGEX = /\x1b\[(\d+(;\d+)*)m/g;

        const ANSI_STYLES = {
            "0":  "</span>",
            "1":  "<span style='font-weight:bold'>",
            "30": "<span style='color:black'>",
            "31": "<span style='color:red'>",
            "32": "<span style='color:green'>",
            "33": "<span style='color:yellow'>",
            "34": "<span style='color:blue'>",
            "35": "<span style='color:magenta'>",
            "36": "<span style='color:cyan'>",
            "37": "<span style='color:white'>",
            "90": "<span style='color:gray'>"
        };

        return text.replace(ANSI_REGEX, (_, codes) => {
            const parts = codes.split(";");

            // Wenn mehrere Codes kommen, z.B. 1;32 → Bold + Green
            let html = "";
            for (const code of parts) {
                if (ANSI_STYLES[code]) {
                    html += ANSI_STYLES[code];
                }
            }

            return html;
        });
    }

    const form = document.getElementById("model-upload-form");
    form.addEventListener("submit", function(e) {
        e.preventDefault(); // Normales Submit verhindern
        container.textContent = ""; // alte Ausgabe löschen
        wrapper.style.display = "block"; // erst jetzt alles anzeigen
        reloadBtn.style.display = "none";

        const formData = new FormData(form);

        fetch(form.action, {
            method: "POST",
            body: formData
        }).then(response => {
            if (!response.body) throw new Error("Streams not supported!");

            const reader = response.body.getReader();
            const decoder = new TextDecoder("utf-8");

            function read() {
                reader.read().then(({done, value}) => {
                    if (done) {
                        appendOutput("\n--- Done ---\n");
                        reloadBtn.style.display = "inline-block";
                        return;
                    }
                    appendOutput(decoder.decode(value));
                    read();
                }).catch(err => {
                    appendOutput("\n--- ERROR: " + err.message + " ---\n");
                    reloadBtn.style.display = "inline-block";
                });
            }

            read();
        }).catch(err => {
            appendOutput("\n--- ERROR: " + err.message + " ---\n");
            reloadBtn.style.display = "inline-block";
        });
    });

    reloadBtn.addEventListener("click", function() {
        location.reload();
    });
});
</script>

<?php
include_once("footer.php");
?>
