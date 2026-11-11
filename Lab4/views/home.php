<html>
<meta charset=utf-8>
<head>
    <title>Menu</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<?php
include "menu.php";
?>

<h2><b>Welcome! <br></b></h2>
<p>Число відвідувань:
<?php
    $filename = "counter.txt";
    $fp = fopen($filename,"r");
    if ($fp) {
        $counter=fgets($fp,10);
        fclose($fp);
    } else {
        $counter=0;
    }
    $counter++;
    print $counter;
    $fp = fopen($filename,"w");
    if ($fp) {
        $ii=fputs($fp,$counter);
        fclose($fp);
    }
?></p>
<?php
include "footer.php";
?>
</body>
</html>
