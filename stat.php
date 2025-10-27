<?php
include("functions.php");

// Retrieve bounding box data and aspect ratios
$result = rquery("SELECT w, h FROM annotation WHERE deleted = 0");
$aspect_ratios = [];
$widths = [];
$heights = [];
while ($row = $result->fetch_assoc()) {
    if ($row["h"]) $aspect_ratios[] = $row["w"] / $row["h"];
    $widths[] = $row['w'];
    $heights[] = $row['h'];
}

// User annotation counts
$result = rquery("SELECT * FROM (SELECT user.name, COUNT(annotation.id) AS count
                    FROM user
                    LEFT JOIN annotation ON user.id = annotation.user_id
                    GROUP BY user.id) a ORDER BY a.count");
$user_names = [];
$annotation_counts = [];
while($row = $result->fetch_assoc()) {
    $user_names[] = $row["name"];
    $annotation_counts[] = $row["count"];
}

// Annotations over time
$annotation_count_result = rquery("SELECT COUNT(id) as count, DATE(modified) as date
                                   FROM annotation
                                   WHERE deleted = 0
                                   AND image_id NOT IN (SELECT id FROM image WHERE deleted = 1)
                                   GROUP BY DATE(modified)");

$data = [];
$i = 0;
while($row = mysqli_fetch_assoc($annotation_count_result)) {
    if($i != 0) $data[] = ['date' => $row['date'], 'count' => $row['count']];
    $i++;
}

// Annotations per image/date
$annotation_count_result = rquery("SELECT COUNT(id) as count, DATE_FORMAT(modified, '%Y-%m-%d') datehour
                                   FROM annotation
                                   WHERE deleted = 0
                                   AND image_id NOT IN (SELECT id FROM image WHERE deleted = 1)
                                   GROUP BY image_id, datehour
                                   ORDER BY datehour");

$data_images = [];
$i = 0;
while($row = mysqli_fetch_assoc($annotation_count_result)) {
    if($i != 0) $data_images[] = ['date' => $row['datehour'], 'count' => $row['count']];
    $i++;
}

// Category counts
$category_count_result = rquery("SELECT category.name, COUNT(*) as count
                                 FROM category
                                 INNER JOIN annotation ON category.id = annotation.category_id
                                 GROUP BY category.id");
$category_data = [];
while ($row = mysqli_fetch_assoc($category_count_result)) {
    $category_data[] = [
        "x" => [$row['name']],
        "y" => [$row['count']],
        "type" => "bar",
        "name" => $row['name'],
        "marker" => [
            "color" => "rgba(0,200,255,0.7)",
            "line" => ["color" => "rgba(0,200,255,1)", "width" => 2]
        ]
    ];
}

include("header.php");
?>
<body>
<div id="category_chart"></div>
<div id="annotation_chart"></div>
<div id="width_histogram"></div>
<div id="height_histogram"></div>
<div id="user_plot"></div>
<div id="aspect_ratio_plot"></div>

<script>
var dark_layout = {
    plot_bgcolor: '#1e1e1e',
    paper_bgcolor: '#1e1e1e',
    font: { color: '#ffffff' },
    xaxis: { gridcolor: '#444', zerolinecolor: '#666' },
    yaxis: { gridcolor: '#444', zerolinecolor: '#666' },
    title: { font: { color: '#ffffff' } }
};

// Category chart
Plotly.newPlot('category_chart', <?php echo json_encode($category_data); ?>,
               Object.assign({}, dark_layout, {title: 'Annotations per Category'}));

// Annotations over time
var x = [], y = [];
<?php foreach($data as $point) { ?>
    x.push("<?php echo $point['date']; ?>");
    y.push(<?php echo $point['count']; ?>);
<?php } ?>
Plotly.newPlot('annotation_chart', [{x:x, y:y, type:'scatter', line:{color:'#00ff88'}}],
               Object.assign({}, dark_layout, {title:'Annotations Created Over Time'}));

// Bounding box histograms
var width_data = <?php echo json_encode($widths); ?>;
var height_data = <?php echo json_encode($heights); ?>;
Plotly.newPlot('width_histogram', [{x:width_data, type:'histogram', marker:{color:'#ff7f0e'}}],
               Object.assign({}, dark_layout, {title:'Bounding Box Width Distribution'}));
Plotly.newPlot('height_histogram', [{x:height_data, type:'histogram', marker:{color:'#d62728'}}],
               Object.assign({}, dark_layout, {title:'Bounding Box Height Distribution'}));

// User annotation stats
Plotly.newPlot('user_plot', [{
    x: <?php echo json_encode($user_names); ?>,
    y: <?php echo json_encode($annotation_counts); ?>,
    type: 'bar',
    marker: { color:'#9467bd' }
}], Object.assign({}, dark_layout, {title:'User Annotation Statistics'}));

// Aspect ratios
Plotly.newPlot('aspect_ratio_plot', [{
    x: <?php echo json_encode($aspect_ratios); ?>,
    type:'histogram',
    nbinsx: 1000,
    marker: { color:'#1f77b4' }
}], Object.assign({}, dark_layout, {title:'Aspect Ratio Statistics'}));
</script>

<?php
$sql = "SELECT id, name FROM category";
$result = rquery($sql);
$categories = [];
while($row = $result->fetch_assoc()) $categories[$row["id"]] = $row["name"];

include("footer.php");
?>
<script>
load_dynamic_content();
</script>
