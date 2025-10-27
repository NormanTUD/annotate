<?php
// Connect to the database
include("functions.php");


// Retrieve the data
$sql = "SELECT w, h FROM annotation WHERE deleted = 0";
$result = rquery($sql);

$aspect_ratios = array();

if ($result->num_rows > 0) {
	while($row = $result->fetch_assoc()) {
		if($row["h"]) {
			$aspect_ratio = $row["w"] / $row["h"];
			$aspect_ratios[] = $aspect_ratio;
		}
	}
}

    $sql = "SELECT * FROM (SELECT user.name, COUNT(annotation.id) AS count FROM user LEFT JOIN annotation ON user.id = annotation.user_id GROUP BY user.id) a order by a.count";
    $result = rquery($sql);

    $user_names = array();
    $annotation_counts = array();

    if ($result->num_rows > 0) {
      while($row = $result->fetch_assoc()) {
        $user_names[] = $row["name"];
        $annotation_counts[] = $row["count"];
      }
    }

// Get the count of annotations for each category
$category_count_query = "SELECT category.name, COUNT(*) as count FROM category INNER JOIN annotation ON category.id = annotation.category_id GROUP BY category.id";
$category_count_result = rquery($category_count_query);
$annotation_count_query = "SELECT COUNT(id) as count, DATE(modified) as date FROM annotation where deleted = 0 and image_id not in (select id from image where deleted = 1) GROUP BY DATE(modified)";

$annotation_count_result = rquery($annotation_count_query);

$data = [];
$i = 0;
while($row = mysqli_fetch_assoc($annotation_count_result)) {
  if($i != 0) {
	  $data[] = ['date' => $row['date'], 'count' => $row['count']];
  }
  $i++;
}

// Get the count of annotations for each category
$category_count_query = "SELECT category.name, COUNT(*) as count FROM category INNER JOIN annotation ON category.id = annotation.category_id GROUP BY category.id";
$category_count_result = rquery($category_count_query);
$annotation_count_query = "SELECT COUNT(id) as count, DATE_FORMAT(modified, '%Y-%m-%d') datehour FROM annotation where deleted = 0 and image_id not in (select id from image where deleted = 1) GROUP BY image_id, datehour ORDER BY datehour";

$annotation_count_result = rquery($annotation_count_query);

$data_images = [];
$i = 0;
while($row = mysqli_fetch_assoc($annotation_count_result)) {
  if($i != 0) {
	  $data_images[] = ['date' => $row['datehour'], 'count' => $row['count']];
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

<div id="user_plot"></div>

  <script>
    // Create the plot

    var data = [{
      x: <?php echo json_encode($user_names); ?>,
      y: <?php echo json_encode($annotation_counts); ?>,
      type: 'bar'
    }];

    var layout = {
      title: 'User Annotation Statistics',
      xaxis: {
        title: 'User'
      },
      yaxis: {
        title: 'Number of Annotations'
      }
    };

    Plotly.newPlot('user_plot', data, layout);
  </script>

  <div id="aspect_ratio_plot"></div>


  <script>
    // Create the plot
    var trace1 = {
      x: <?php echo json_encode($aspect_ratios); ?>,
      type: 'histogram',
      marker: {
        color: '#1f77b4',
      },
      nbinsx: 1000
    };

    var data = [trace1];

    var layout = {
      title: 'Aspect Ratio Statistics',
      xaxis: {
        title: 'Aspect Ratio (Width/Height)'
      },
      yaxis: {
        title: 'Frequency'
      }
    };

    Plotly.newPlot('aspect_ratio_plot', data, layout);
  </script>

<?php
// Query zur Abfrage der Kategorien und ihrer IDs
$sql = "SELECT id, name FROM category";
$result = rquery($sql);
$categories = array();

// Schleife, um die Kategorien in ein Array zu speichern
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $categories[$row["id"]] = $row["name"];
    }
}

	include("footer.php");
?>
<script>
	load_dynamic_content();
</script>
