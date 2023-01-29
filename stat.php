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
  </body>
</html>
