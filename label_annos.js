function rect_bbox_from_element(el) {
	let x = parseFloat(el.getAttribute('x')) || 0;
	let y = parseFloat(el.getAttribute('y')) || 0;
	let w = parseFloat(el.getAttribute('width')) || 0;
	let h = parseFloat(el.getAttribute('height')) || 0;
	return { x, y, width: w, height: h };
}

function rect_bbox_from_element(el) {
	let x = parseFloat(el.getAttribute('x')) || 0;
	let y = parseFloat(el.getAttribute('y')) || 0;
	let w = parseFloat(el.getAttribute('width')) || 0;
	let h = parseFloat(el.getAttribute('height')) || 0;
	return { x, y, width: w, height: h };
}

function boxes_intersect(a, b) {
	return a.x < b.x + b.width && a.x + a.width > b.x &&
		a.y < b.y + b.height && a.y + a.height > b.y;
}

function color_from_string(str) {
	let h = 0;
	for (let i = 0; i < str.length; i++) h = (h * 31 + str.charCodeAt(i)) >>> 0;
	let hue = h % 360;
	return `hsl(${hue},70%,50%)`;
}

function get_category_for_annotation(rect, svg, img) {
	if (!rect || !svg) return 'unknown';
	let annot_box = rect_bbox_from_element(rect);

	// 1) search svg <text> elements that overlap the annotation rect
	let texts = svg.querySelectorAll('text');
	for (let t of texts) {
		try {
			// use getBBox to get true rendered size/position
			let tb = t.getBBox();
			let text_box = { x: tb.x, y: tb.y, width: tb.width, height: tb.height };
			if (boxes_intersect(annot_box, text_box)) {
				let txt = (t.textContent || '').trim();
				if (txt) return txt;
			}
		} catch (e) {
			// some browsers may throw on getBBox if SVG not rendered yet; ignore
		}
	}

	// 2) if no intersecting text found, try "nearest above" heuristic:
	let candidates = [];
	for (let t of texts) {
		let txt = (t.textContent || '').trim();
		if (!txt) continue;
		try {
			let tb = t.getBBox();
			// prefer texts whose x is close and y is above rect.y
			let dx = Math.abs((tb.x + tb.width/2) - (annot_box.x + annot_box.width/2));
			let dy = (tb.y + tb.height) - annot_box.y; // negative if above
			candidates.push({ node: t, dx, dy, txt });
		} catch (e) {}
	}
	if (candidates.length) {
		candidates.sort((a,b) => {
			// prefer small absolute dx and text above (dy < 10) or small positive dy
			let sa = Math.abs(a.dx) + Math.max(0, a.dy);
			let sb = Math.abs(b.dx) + Math.max(0, b.dy);
			return sa - sb;
		});
		return candidates[0].txt;
	}

	// 3) fallback: if caller provided an image and there are overlay .r6o-editor divs, use them
	if (img) {
		let divs = document.querySelectorAll('.r6o-editor');
		if (divs.length) {
			let imgRect = img.getBoundingClientRect();
			let svgRect = svg.getBoundingClientRect();
			// derive scale from svg viewBox if possible
			let scaleX = 1, scaleY = 1;
			try {
				let vb = svg.viewBox.baseVal;
				scaleX = vb.width / svgRect.width;
				scaleY = vb.height / svgRect.height;
			} catch (e) {}
			for (let div of divs) {
				let d = div.getBoundingClientRect();
				let relX = (d.left - imgRect.left) * scaleX;
				let relY = (d.top - imgRect.top) * scaleY;
				let relW = d.width * scaleX;
				let relH = d.height * scaleY;
				let div_box = { x: relX, y: relY, width: relW, height: relH };
				if (boxes_intersect(annot_box, div_box)) {
					let labelSpan = div.querySelector('.r6o-label');
					if (labelSpan) return labelSpan.textContent.trim();
				}
			}
		}
	}

	return 'unknown';
}

function clear_previous_labels(svg) {
	if (!svg) return;
	let annotated = svg.querySelectorAll('[data-annotated="1"]');
	for (let n of annotated) n.remove();
}

function annotate_svg(svg, img) {
	if (!svg) return;
	clear_previous_labels(svg);

	svg.querySelectorAll('g.a9s-annotation').forEach((g) => {
		let rect = g.querySelector('rect.a9s-inner');
		if (!rect) return;
		let category = get_category_for_annotation(rect, svg, img);

		let box = rect_bbox_from_element(rect);
		let color = color_from_string(category);

		let bg = document.createElementNS("http://www.w3.org/2000/svg", "rect");
		bg.setAttribute("x", box.x);
		bg.setAttribute("y", box.y - 20);
		bg.setAttribute("width", Math.max(30, Math.min(box.width, category.length * 8)));
		bg.setAttribute("height", 20);
		bg.setAttribute("fill", color);
		bg.setAttribute("opacity", 0.7);
		bg.setAttribute("data-annotated", "1");

		let text = document.createElementNS("http://www.w3.org/2000/svg", "text");
		text.setAttribute("x", box.x + 4);
		text.setAttribute("y", box.y - 6);
		text.setAttribute("fill", "#fff");
		text.setAttribute("font-size", "14");
		text.setAttribute("font-family", "Arial, sans-serif");
		text.textContent = category;
		text.setAttribute("data-annotated", "1");

		svg.appendChild(bg);
		svg.appendChild(text);
	});
}

function throttle(func, limit) {
	let lastCall = 0;
	return function() {
		const now = Date.now();
		if (now - lastCall >= limit) {
			lastCall = now;
			func.apply(this, arguments);
		}
	};
}

function watch_svg_auto() {
	// Bild und SVG automatisch suchen
	const img = Array.from(document.images).find(i => i.src.includes('print_image.php?filename='));
	const svg = document.querySelector('svg.a9s-annotationlayer');

	if (!img || !svg) {
		// Falls noch nicht geladen, retry nach kurzer Zeit
		setTimeout(watch_svg_auto, 500);
		return;
	}

	const throttledUpdate = throttle(() => annotate_svg(svg, img), 200); // max 5x/sec

	const observer = new MutationObserver(throttledUpdate);
	observer.observe(svg, { childList: true, subtree: true, attributes: true });

	annotate_svg(svg, img);
}

watch_svg_auto();
