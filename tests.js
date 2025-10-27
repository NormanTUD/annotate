async function run_tests() {
	console.log("=== RUNNING TESTS ===");

	let passed = 0;
	let failed = 0;

	function assert(condition, msg) {
		if (condition) {
			console.log("✓ PASS:", msg);
			passed++;
		} else {
			console.error("✖ FAIL:", msg);
			failed++;
		}
	}

	// --- uuidv4 ---
	console.log("\n--- Testing uuidv4 ---");
	const id1 = uuidv4();
	const id2 = uuidv4();
	assert(typeof id1 === "string", "uuidv4 returns a string");
	assert(id1.length === 36, "uuidv4 returns correct length (36 chars)");
	assert(id1 !== id2, "uuidv4 produces unique values");
	assert(/^[0-9a-f-]+$/.test(id1), "uuidv4 contains only valid hex chars and dashes");

	// --- getNewURL ---
	console.log("\n--- Testing getNewURL ---");
	const url1 = getNewURL("http://example.com?a=1", "a", "2");
	assert(url1 === "http://example.com?a=2", "getNewURL updates existing parameter");

	const url2 = getNewURL("http://example.com", "b", "5");
	assert(url2 === "http://example.com?b=5", "getNewURL adds new parameter to URL without params");

	const url3 = getNewURL("http://example.com?x=1&y=2", "y", "99");
	assert(url3.includes("y=99") && url3.includes("x=1"), "getNewURL updates param while keeping others");

	const url4 = getNewURL("http://example.com?a=1&b=2&c=3", "b", "new");
	assert(url4.includes("a=1") && url4.includes("b=new") && url4.includes("c=3"), "getNewURL handles multiple params");

	// --- iou (Intersection over Union) ---
	console.log("\n--- Testing iou ---");
	const boxA = [0, 0, 10, 10];
	const boxB = [5, 5, 15, 15];
	const boxC = [20, 20, 30, 30];
	const boxD = [0, 0, 10, 10]; // identical to boxA

	assert(iou(boxA, boxB) > 0 && iou(boxA, boxB) < 1, "iou overlapping boxes between 0 and 1");
	assert(iou(boxA, boxC) === 0, "iou non-overlapping boxes = 0");
	assert(iou(boxA, boxD) === 1, "iou identical boxes = 1");
	assert(Math.abs(iou(boxA, boxB) - 0.142857) < 0.001, "iou calculates correct value for partial overlap");

	// --- getShape ---
	console.log("\n--- Testing getShape ---");
	const arr1 = [[1,2],[3,4]];
	const arr2 = [[[1,2],[3,4]],[[5,6],[7,8]]];
	const arr3 = [1,2,3,4];
	const arr4 = [];

	assert(JSON.stringify(getShape(arr1)) === JSON.stringify([2,2]), "getShape works for 2D array");
	assert(JSON.stringify(getShape(arr2)) === JSON.stringify([2,2,2]), "getShape works for 3D array");
	assert(JSON.stringify(getShape(arr3)) === JSON.stringify([4]), "getShape works for 1D array");
	assert(JSON.stringify(getShape(arr4)) === JSON.stringify([0]), "getShape handles empty array");

	// --- get_names_from_ki_anno ---
	console.log("\n--- Testing get_names_from_ki_anno ---");
	const sampleAnno1 = [
		{ body: [{ value: "Alice" }, { value: "Bob" }] },
		{ body: [{ value: "Alice" }] }
	];
	const names1 = get_names_from_ki_anno(sampleAnno1);
	assert(names1["Alice"] === 2 && names1["Bob"] === 1, "get_names_from_ki_anno counts correctly");

	const sampleAnno2 = [];
	const names2 = get_names_from_ki_anno(sampleAnno2);
	assert(Object.keys(names2).length === 0, "get_names_from_ki_anno handles empty array");

	const sampleAnno3 = [
		{ body: [{ value: "Cat" }, { value: "Cat" }, { value: "Dog" }] }
	];
	const names3 = get_names_from_ki_anno(sampleAnno3);
	assert(names3["Cat"] === 2 && names3["Dog"] === 1, "get_names_from_ki_anno counts duplicates in same annotation");

	// --- getUrlParam ---
	console.log("\n--- Testing getUrlParam ---");
	// Save original location
	const originalSearch = window.location.search;

	// Test with mock (simplified - depends on current URL)
	// Note: Diese Tests funktionieren nur wenn die URL entsprechende Parameter hat
	// oder wir müssten window.location mocken
	const defaultVal = getUrlParam('nonexistent_param', 42);
	assert(defaultVal === 42, "getUrlParam returns default for non-existent param");

	const nanVal = getUrlParam('string_param', 100);
	assert(nanVal === 100, "getUrlParam returns default for non-numeric param");

	// --- sleep ---
	console.log("\n--- Testing sleep ---");
	const startTime = Date.now();
	await sleep(100);
	const elapsed = Date.now() - startTime;
	assert(elapsed >= 100 && elapsed < 150, "sleep waits approximately correct time");

	// --- get_annotate_element (validation only) ---
	console.log("\n--- Testing get_annotate_element validation ---");
	const badAnno1 = get_annotate_element("dog", -1, 0, 10, 10);
	assert(badAnno1 === null, "get_annotate_element rejects negative x_start");

	const badAnno2 = get_annotate_element("dog", 0, -5, 10, 10);
	assert(badAnno2 === null, "get_annotate_element rejects negative y_start");

	const badAnno3 = get_annotate_element("dog", 0, 0, 0, 10);
	assert(badAnno3 === null, "get_annotate_element rejects zero width");

	const badAnno4 = get_annotate_element("dog", 0, 0, 10, -10);
	assert(badAnno4 === null, "get_annotate_element rejects negative height");

	const badAnno5 = get_annotate_element("dog", 1.5, 0, 10, 10);
	assert(badAnno5 === null, "get_annotate_element rejects float x_start");

	// Valid call (needs DOM element to be present)
	if ($("#image").length > 0) {
		const goodAnno = get_annotate_element("cat", 10, 20, 100, 200);
		assert(goodAnno !== null && goodAnno.type === "Annotation", "get_annotate_element returns valid annotation object");
		assert(goodAnno.body[0].value === "cat", "get_annotate_element sets correct label");
		assert(goodAnno.target.selector.value === "xywh=pixel:10,20,100,200", "get_annotate_element formats coordinates correctly");
	}

	// --- processModelOutput (basic structure test) ---
	console.log("\n--- Testing processModelOutput ---");
	// Simplified test with known input
	if (typeof imgsz !== 'undefined') {
		// Mock YOLO output: res[0] = data where data[feature_idx][prediction_idx]
		// Features: [x, y, w, h, class_score_0, class_score_1, ...]
		// We need to transpose: each feature is an array of values across predictions
		const mockOutput = [
			[
				[400, 200],      // x values for 2 predictions
				[400, 200],      // y values
				[100, 50],       // w values
				[100, 50],       // h values
				[0.1, 0.2],      // class 0 scores
				[0.9, 0.1]       // class 1 scores
			]
		];

		const result = processModelOutput(mockOutput, 0.5, 0.5);
		assert(result.boxes.length === 1, "processModelOutput filters low confidence predictions");
		assert(result.classes[0] === 1, "processModelOutput selects correct class");
		assert(result.scores[0] === 0.9, "processModelOutput returns correct score");
	}

	console.log(`\n=== TESTS DONE: ${passed} passed, ${failed} failed ===`);

	return failed;
}
