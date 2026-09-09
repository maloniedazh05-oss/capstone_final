<?php 
if($_SERVER['REQUEST_METHOD'] == "POST" && isset($_FILES['historyFile'])) {
    $tempFile = file_get_contents($_FILES['historyFile']['tmp_name']);

    $fileContent = json_decode($tempFile);

    if(!is_array($fileContent)) {
        echo "Invalid text Content! Must be JSON string Format.<br><a href='../index.php'>Confirm</a>";
        exit;
    }

    $newArray = [];

    foreach($fileContent as $content) {
        $tempContent = (int)$content;
        array_push($newArray, $tempContent);
    }

    echo "Return: ". json_encode($newArray);

    
    //$jsonString = json_encode($fileContent);   
/*
    # [3,2,4] check if data is JSON string format
    $jsonData = json_decode($fileContent); // Convert to PHP value
    echo $fileContent;
    echo $jsonData;

    if(!is_array($jsonData)) {
        echo "Invalid JSON data";
        exit;
    }
    echo "<a href='../index.php'>Back</a>";
    } else echo "Invalid file format!";

    $string = "apple,banana,orange";

// Split the string by the comma delimiter
$array = explode(",", $string);
*/
    
}
?>

<!--

if (isset($_FILES['forecastData'])) {
    
    // 2. Get the secret temporary path where the actual file content lives
    $temporaryFilePath = $_FILES['forecastData']['tmp_name'];
    
    // 3. Read the actual text inside that file
    $fileContent = file_get_contents($temporaryFilePath);
    
    // 4. Output or use the data
    echo "Here is your file content: " . $fileContent; 
    // Example Output: 
    
    // 5. Turn it into a real PHP array to verify it worked
    $array = json_decode($fileContent, true);
    print_r($array);

} else {
    echo "No file was uploaded or enctype attribute is missing in HTML.";
}
?>