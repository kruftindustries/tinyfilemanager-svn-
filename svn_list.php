<?php

/**
 * This function parses the output of svn list --xml into a HTML formatted array of arrays
 * displaying a webpage on GET and returning SVN list command output on POST
 * if proc_open() is allowed on your server, it runs without any plugins or special features
 * on php version > 8.0 (php svn curl plugin doesn't work in newer versions).
 * This is simply to demonstrate svn list output to php / js / html
 * Throw on your machine and update the $context object to browse a repository
 * Should not require modifying any local server references, uses relative paths wherever possible.
 *
 * $context->username = '';
 * $context->password = '';
 * $context->url = '';
 *
 */
 
 function simpleXmlToArray($fileNames) {
    try {
    $xml = simplexml_load_string($fileNames);
    } catch (Exception $e) {
       // handle the error
       echo '$xmlstr is not a valid xml string';
    }
    $filepath = strval($xml->list->attributes()->path);
    unset($xml->list->attributes()->path);
    $xml = $xml->list;
    foreach ($xml->entry as $Item) {
        $Item->addChild('type', $Item->attributes()->kind);
        $Item->addChild('path', $filepath);
        $Item->addChild('author', $Item->commit->author);
        $Item->addChild('date', $Item->commit->date);
        $Item->addChild('revision', $Item->commit->attributes()->revision);
        unset($Item->attributes()->kind);
        unset($Item->commit);
    }
    return json_decode(json_encode($xml), true)['entry'];
}
 // This function executes a command and returns the output
function Execute($cmd, $context) {

    $output = "";
    $err = false;
    $descriptorspec = array (
      0 => array('pipe', 'r'),
      1 => array('pipe', 'w'),
      2 => array('pipe', 'w')
    );

    $resource = proc_open($cmd, $descriptorspec, $pipes);

    $error = "";
    if (!is_resource($resource))
    {
       return "ERR1";
    }
      
    $handle = $pipes[1];
    $firstline = true;
    while (!feof($handle))
    {
      $line = fgets($handle);
      if ($firstline && empty($line))
      {
        $err = true;
      }
      $firstline = false;
      $output .= rtrim($line);
    }
	
    while (!feof($pipes[2]))
    {
       $error .= fgets($pipes[2]);
    }
    $error = trim($error);
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
   
    proc_close($resource);
    if (!$err)
      return $output;

    $context->error = $error;
    return "";
}

//check if we're doing a GET of the html content or a POST to retrieve the formatted command output

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["dir"]) && $_POST["dir"] != "undefined") {
        $url = parse_url($_POST["dir"], PHP_URL_PATH);
        $keyword =  $url;
    } else {
        $keyword = '/';
    }
  
    // build the SVN list command and parameters
    $context = new StdClass;
    //populate these from the session variables (eventually)
    $context->username = '';
    $context->password = '';
    $context->url = 'https://svn.xiph.org:443';
    
    $context->path = $keyword;
    $context->params = "list \"" . $context->url . '/' . $context->path."\"";   
    $cmd = "/usr/bin/svn" . " --xml" . " --username ".$context->username." --password ".$context->password." --non-interactive ".$context->params;
    $fileNames = Execute($cmd, $context); 
    $path = $context->path;

    $arr = simpleXmlToArray($fileNames);

    // output a link to navigate up one directory
    echo "<a style=\"color:blue;\" onclick=myFunction(\"" . dirname($arr[0]['path']) . "\")>" . $keyword . "</a>";
    // output the html formatted svn list
    echo "<table><tr><th>Name</th><th>Size</th><th>Author</th><th>Modified</th><th>Revision</th></tr><tr>";
    foreach($arr as $key => $value) {
        if ($value['type'] == 'file') {
          echo "<td>" . $value['name'] . "</td>" . "<td>" . $value['size'] . "</td>" . "<td>" . $value['author'] . "</td>" . "<td>" . $value['date'] . "</td>" . "<td>" . $value['revision'] . "</td>";
        }
        if ($value['type'] == 'dir') {
          echo "<td><a style=\"color:blue;\" onclick=myFunction(\"" . $value['path'] . "/" . $value['name'] . "\")>" . $value['name'] . "</a></td>" . "<td>" . "" . "</td>" . "<td>" . $value['author'] . "</td>" . "<td>" . $value['date'] . "</td>" . "<td>" . $value['revision'] . "</td>";
        }
      echo "</tr>";
    }
    echo "</table>";
    
} else {
    //If we're requesting the webpage, return the webpage content, styles and js functions to replace the <p id=demo/> tag with the command output
    echo '
    <style>a:hover{cursor:pointer;}table{font-family:arial,sans-serif;border-collapse:collapse;width:100%}th{border:1px solid #f1f1f1;text-align:left;padding:8px;background-repeat:no-repeat;background-position:center right;background-image:url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABMAAAATCAQAAADYWf5HAAAAkElEQVQoz7XQMQ5AQBCF4dWQSJxC5wwax1Cq1e7BAdxD5SL+Tq/QCM1oNiJidwox0355mXnG/DrEtIQ6azioNZQxI0ykPhTQIwhCR+BmBYtlK7kLJYwWCcJA9M4qdrZrd8pPjZWPtOqdRQy320YSV17OatFC4euts6z39GYMKRPCTKY9UnPQ6P+GtMRfGtPnBCiqhAeJPmkqAAAAAElFTkSuQmCC)}td{border:1px solid #f1f1f1;text-align:left;padding:8px}tr:nth-child(even){background-color:#ddd}</style><script>var host = window.location;
    //window.onload = myFunction;

    function myFunction(url) {
        const xmlhttp = new XMLHttpRequest();
        xmlhttp.open("POST", host, true);
        xmlhttp.setRequestHeader("Content-type","application/x-www-form-urlencoded");
        xmlhttp.send("dir=" + url);
        xmlhttp.onload = function() {
          document.getElementById("demo").innerHTML = this.responseText;
        }
    }
    </script></head><body onload="myFunction();"><p id=demo>Text</p>';
}
?>
