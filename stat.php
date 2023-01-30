<?php
// Connect to the database
include("functions.php");

// Get the count of annotations for each category
$category_count_query = "SELECT category.name, COUNT(*) as count FROM category INNER JOIN annotation ON category.id = annotation.category_id GROUP BY category.id";
$category_count_result = rquery($category_count_query);
$annotation_count_query = "SELECT COUNT(id) as count, DATE(modified) as date FROM annotation where deleted = 0 GROUP BY DATE(modified)";

$annotation_count_result = rquery($annotation_count_query);

$data = [];
$i = 0;
while($row = mysqli_fetch_assoc($annotation_count_result)) {
  if($i != 0) {
	  $data[] = ['date' => $row['date'], 'count' => $row['count']];
  }
  $i++;
}

// Store the count of annotations for each category
$category_data = [];
while ($row = mysqli_fetch_assoc($category_count_result)) {
  $category_data[] = [
    "x" => [$row['name']],
    "y" => [$row['count']],
    "type" => "bar",
    "name" => $row['name'],
    "marker" => [
      "color" => "rgba(55, 128, 191, 0.7)",
      "line" => [
        "color" => "rgba(55, 128, 191, 1.0)",
        "width" => 2
      ]
    ]
  ];
}

// Get the count of annotations per category
$query = "SELECT category.name, COUNT(annotation.id) FROM annotation JOIN category ON annotation.category_id = category.id  where annotation.deleted != 0 GROUP BY category.id";
$result = rquery($query);

$x = array();
$y = array();

while ($row = mysqli_fetch_assoc($result)) {
  array_push($x, $row['name']);
  array_push($y, $row['COUNT(annotation.id)']);
}

// Get the width and height of all bounding boxes
$bounding_box_data_query = "SELECT w, h FROM annotation";
$bounding_box_data_result = rquery($bounding_box_data_query);

$widths = array();
$heights = array();

while ($row = mysqli_fetch_assoc($bounding_box_data_result)) {
  $widths[] = $row['w'];
  $heights[] = $row['h'];
}

// Use Plotly.js to display the statistics
include("header.php");
?>
  <body>
    <div id="category_chart"></div>
<script>
  Plotly.newPlot('category_chart', <?php echo json_encode($category_data); ?>, {
    title: 'Annotations per Category',
    xaxis: {title: 'Category'},
    yaxis: {title: 'Annotation Count'}
  });
</script>
    <div id="annotation_chart"></div>
<script>
  var x = [];
  var y = [];

  <?php foreach($data as $point) { ?>
    x.push("<?php echo $point['date']; ?>");
    y.push(<?php echo $point['count']; ?>);
  <?php } ?>

  Plotly.newPlot('annotation_chart', [{
    x: x,
    y: y,
    type: 'scatter'
  }], {
    title: 'Annotations Created Over Time',
    xaxis: {
      title: 'Date'
    },
    yaxis: {
      title: 'Annotation Count'
    }
  });
</script>
    <div id="annotations_per_category_chart"></div>

    <script>
      Plotly.newPlot('annotations_per_category_chart', [{
        x: <?php echo json_encode($x); ?>,
        y: <?php echo json_encode($y); ?>,
        type: 'bar'
      }], {
        title: 'Annotations per Category'
      });
    </script>

<div id="width_histogram"></div>
<div id="height_histogram"></div>

<script>
  var width_data = <?php echo json_encode($widths); ?>;
  var height_data = <?php echo json_encode($heights); ?>;

  Plotly.newPlot('width_histogram', [{
    x: width_data,
    type: 'histogram',
    name: 'Width Distribution'
  }], {
    title: 'Bounding Box Width Distribution'
  });

  Plotly.newPlot('height_histogram', [{
    x: height_data,
    type: 'histogram',
    name: 'Height Distribution'
  }], {
    title: 'Bounding Box Height Distribution'
  });
</script>
<?php
	include("footer.php");
?>
<script>
	load_dynamic_content();
</script>
