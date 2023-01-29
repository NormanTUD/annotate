<?php
// Connect to the database
include("functions.php");

// Get the count of records for each table
$image_count_query = "SELECT COUNT(*) FROM image";
$category_count_query = "SELECT COUNT(*) FROM category";
$user_count_query = "SELECT COUNT(*) FROM user";
$annotation_count_query = "SELECT COUNT(*) FROM annotation";

$image_count_result = rquery($image_count_query);
$category_count_result = rquery($category_count_query);
$user_count_result = rquery($user_count_query);
$annotation_count_result = rquery($annotation_count_query);

$image_count = mysqli_fetch_assoc($image_count_result)['COUNT(*)'];
$category_count = mysqli_fetch_assoc($category_count_result)['COUNT(*)'];
$user_count = mysqli_fetch_assoc($user_count_result)['COUNT(*)'];
$annotation_count = mysqli_fetch_assoc($annotation_count_result)['COUNT(*)'];

// Use Plotly.js to display the statistics
?>
<html>
  <head>
    <script src="plotly-latest.min.js"></script>
  </head>
  <body>
    <div id="image_chart"></div>
    <div id="category_chart"></div>
    <div id="user_chart"></div>
    <div id="annotation_chart"></div>

    <script>
      Plotly.newPlot('image_chart', [{
        values: [<?php echo $image_count; ?>],
        labels: ['Image'],
        type: 'pie'
      }], {
        title: 'Image Statistics'
      });

      Plotly.newPlot('category_chart', [{
        values: [<?php echo $category_count; ?>],
        labels: ['Category'],
        type: 'pie'
      }], {
        title: 'Category Statistics'
      });

      Plotly.newPlot('user_chart', [{
        values: [<?php echo $user_count; ?>],
        labels: ['User'],
        type: 'pie'
      }], {
        title: 'User Statistics'
      });

      Plotly.newPlot('annotation_chart', [{
        values: [<?php echo $annotation_count; ?>],
        labels: ['Annotation'],
        type: 'pie'
      }], {
        title: 'Annotation Statistics'
      });
    </script>
  </body>
</html>

