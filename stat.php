<?php
// Connect to the database
include("functions.php");

// Get the count of annotations for each category
$category_count_query = "SELECT category.name, COUNT(*) as count FROM category INNER JOIN annotation ON category.id = annotation.category_id GROUP BY category.id";
$category_count_result = rquery($category_count_query);
$annotation_count_query = "SELECT COUNT(id) as count, DATE(modified) as date FROM annotation GROUP BY DATE(modified)";

$annotation_count_result = rquery($annotation_count_query);

$data = [];
while($row = mysqli_fetch_assoc($annotation_count_result)) {
  $data[] = ['date' => $row['date'], 'count' => $row['count']];
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
$query = "SELECT category.name, COUNT(annotation.id) FROM annotation JOIN category ON annotation.category_id = category.id GROUP BY category.id";
$result = rquery($query);

$x = array();
$y = array();

while ($row = mysqli_fetch_assoc($result)) {
  array_push($x, $row['name']);
  array_push($y, $row['COUNT(annotation.id)']);
}

$query = "SELECT image.filename, COUNT(annotation.id) FROM annotation JOIN image ON annotation.image_id = image.id GROUP BY image.id";
$result = rquery($query);

/*
$number_annotation_per_image_x = array();
$number_annotation_per_image_y = array();

while ($row = mysqli_fetch_assoc($result)) {
  array_push($number_annotation_per_image_x, $row['filename']);
  array_push($number_annotation_per_image_y, $row['COUNT(annotation.id)']);
}
 */

// Use Plotly.js to display the statistics
?>
<html>
  <head>
    <script src="plotly-latest.min.js"></script>
  </head>
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

<!--
    <div id="annotations_per_image_chart"></div>

    <script>
      Plotly.newPlot('annotations_per_image_chart', [{
        x: <?php echo json_encode($number_annotation_per_image_x); ?>,
        y: <?php echo json_encode($number_annotation_per_image_y); ?>,
        type: 'bar'
      }], {
        title: 'Annotations per Image'
      });
    </script>
-->
  </body>
</html>
