<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo $page_title;?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" type="text/css" media="screen" href="<?php echo $app['assets']['css'];?>styles.css" />
    <!-- <script src="<?php echo $app['assets']['js'];?>main.js"></script> -->
</head>
<body>
<?php
if($actionFile)
    include $actionFile['fullPath'];
else
    echo 'Action view file not applicable!';
?>
</body>
</html>