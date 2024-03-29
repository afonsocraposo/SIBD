<?php
include("database.php");
$db = new Database();
$dbh = $db->connect();
?>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Appointments - Day</title>
    <link rel="stylesheet" type="text/css" href="style.css">
    <link href="calendar.css" type="text/css" rel="stylesheet" />
    <style>
        body {
            text-align: center;
        }

        #popup {
            width: 50%;
            padding: 20px;
            display: none;
            position: fixed;
            background-color: white;
            text-align: center;
            margin: auto;
            border-style: solid;
            top: 20%;
            left: 25%;
            border-width: 1px;
            box-shadow: 0 4px 8px 0 rgba(0, 0, 0, 0.2), 0 6px 20px 0 rgba(0, 0, 0, 0.19);
        }
    </style>
</head>

<body>
    <?php

    $value_date = isset($_GET['date']) ? $_GET['date'] : "";
    $value_client_VAT = isset($_GET['client']) ? $_GET['client'] : "";

    $value_rm_VAT = isset($_POST['VAT']) ? $_POST['VAT'] : "";
    $value_rm_dt = isset($_POST['dt']) ? $_POST['dt'] : "";

    $value_add_doctor = isset($_POST['add_doctor']) ? $_POST['add_doctor'] : "";
    $value_add_description = isset($_POST['add_description']) ? $_POST['add_description'] : "";
    $value_add_client = isset($_POST['add_client']) ? $_POST['add_client'] : "";
    $value_add_timestamp = isset($_POST['add_timestamp']) ? $_POST['add_timestamp'] : "";

    if (!empty($value_add_doctor) && !empty($value_add_description) && !empty($value_add_client) && !empty($value_add_timestamp)) {
        $query_add = "INSERT INTO appointment (VAT_doctor, VAT_client, date_timestamp, description)
    VALUES (?, ?, ?, ?)";
        $stmt = $dbh->prepare($query_add);
        $stmt->bindParam(1, $value_add_doctor);
        $stmt->bindParam(2, $value_add_client);
        $stmt->bindParam(3, $value_add_timestamp);
        $stmt->bindParam(4, $value_add_description);
        if (!$stmt->execute()) {
            print("Something went wrong when fetching available doctors");
        }
    }

    $query_clients = "SELECT name, VAT FROM client ORDER BY name ASC";
    $stmt = $dbh->prepare($query_clients);
    if (!$stmt->execute()) {
        print("Something went wrong when fetching available doctors");
    } else {
        if ($stmt->rowCount() > 0) {
            $result_clients = $stmt->fetchAll();
            echo "<datalist id='clients'>";
            foreach ($result_clients as &$client) {
                echo "<option value=\"" . $client['VAT'] . "\">" . $client['name'] . "</option>";
            }
            echo "</datalist>";
        }
    }

    $query_available_doctors = "SELECT employee.name as name, doctor.VAT as VAT
    FROM employee
    INNER JOIN doctor
    ON employee.VAT = doctor.VAT
    WHERE doctor.VAT NOT IN
    (SELECT appointment.VAT_doctor
    FROM appointment 
    INNER JOIN employee 
    ON appointment.VAT_doctor = employee.VAT
    WHERE appointment.date_timestamp LIKE CONCAT(?,' ',?,':%'))
    ORDER BY employee.name ASC";
    $stmt = $dbh->prepare($query_available_doctors);
    for ($i = 9; $i < 17; $i++) {
        $h = sprintf("%'.02d", $i);

        $stmt->bindParam(1, $value_date);
        $stmt->bindParam(2, $h);
        if (!$stmt->execute()) {
            print("Something went wrong when fetching available doctors");
        } else {
            if ($stmt->rowCount() > 0) {
                $result_doctors = $stmt->fetchAll();
                echo "<datalist id='doctors$i'>";
                foreach ($result_doctors as &$doctor) {
                    echo "<option value=\"" . $doctor['VAT'] . "\">" . $doctor['name'] . "</option>";
                }
                echo "</datalist>";
            }
        }
    }

    if (!empty($value_date)) {

        if (!empty($value_rm_VAT) && !empty($value_rm_dt)) {
            $query_delete = "DELETE FROM appointment WHERE VAT_doctor = ? AND date_timestamp LIKE CONCAT(?,'%')";
            $stmt = $dbh->prepare($query_delete);
            $stmt->bindParam(1, $value_rm_VAT);
            $stmt->bindParam(2, $value_rm_dt);
            if (!$stmt->execute()) {
                print("Something went wrong when deleting the appointment");
            }
        }

        $query_count = "SELECT client.name as Cname, employee.name as Dname, HOUR(appointment.date_timestamp) as dt, appointment.description as description, appointment.VAT_doctor as DVAT, appointment.date_timestamp as date_timestamp
        FROM appointment 
        INNER JOIN employee 
        ON appointment.VAT_doctor = employee.VAT
        INNER JOIN client
        ON appointment.VAT_client = client.VAT
        WHERE appointment.date_timestamp LIKE CONCAT(?,'%')
        ORDER BY appointment.date_timestamp, employee.name ASC";

        $stmt = $dbh->prepare($query_count);
        $stmt->bindParam(1, $value_date);

        $result_appointments;
        if (!$stmt->execute()) {
            print("Something went wrong when fetching the appointments");
        } else {
            if ($stmt->rowCount() > 0) {
                $result_appointments = $stmt->fetchAll();
            }
        }

        date_default_timezone_set('Europe/London');
        $current_date = time();
        $prev_day = date("Y-m-d", strtotime($value_date) - 60 * 60 * 24);
        $next_day = date("Y-m-d", strtotime($value_date) + 60 * 60 * 24);
        $month = sprintf("%'.02d", date("m", strtotime($value_date)));
        $year = sprintf("%'.02d", date("Y", strtotime($value_date)));
        echo "<div style='width: 100%;'>
        <button name='' value='' style='font-size:2em;' onclick=\"location.href='" . $db->url() . "clients.php'\">&#127968;</button>
        <br><br>
        <button name='' value='' style='font-size:2em;' onclick=\"location.href='" . $db->url() . "appointment.php?date=$prev_day'\"><</button>
        <button name='' value='' style='font-size:2em;' onclick=\"location.href='" . $db->url() . "appointments.php?month=$month&year=$year'\">	&#128197;</button>
        <button name='' value='' style='font-size:2em;' onclick=\"location.href='" . $db->url() . "appointment.php?date=$next_day'\">></button>
        </div>";

        echo "<h1>" . $value_date . "</h1>";


        $c = 0;
        $delete = false;
        echo ("<table>\n");
        echo ("<tr class='header'><td>Hour</td><td>Doctor</td><td>Client</td><td>Description</td><td>&#128465;</td></tr>\n");
        for ($hour = 9; $hour < 17; $hour++) {
            $date = $value_date . " " . sprintf("%'.02d:00", $hour);
            if (strtotime($date) >= $current_date) {
                echo "<tr class='hour' onclick='prompt(\"$date\", \"$value_client_VAT\")'><td>" . sprintf("%'.02d:00", $hour) . "</td></tr>";
                $delete = true;
            } else {
                echo "<tr class='hour past'><td>" . sprintf("%'.02d:00", $hour) . "</td></tr>";
            }
            if (!empty($result_appointments) && $c < count($result_appointments)) {
                while ($result_appointments[$c]['dt'] == $hour) {
                    echo "<tr onclick=\"location.href='" . $db->url() . "consultation.php?VAT=" . $result_appointments[$c]["DVAT"] . "&timestamp=" . $result_appointments[$c]["date_timestamp"] . "'\"><td></td><td>" . $result_appointments[$c]['Dname'] . "</td><td>" . $result_appointments[$c]['Cname'] . "</td><td>" . $result_appointments[$c]['description'] . "</td>
                        <td>
                            <form action='' method='post'>
                                <input style='display:none' name='VAT' value='" . $result_appointments[$c]['DVAT'] . "'>
                                <input style='display:none' name='dt' value='" . $result_appointments[$c]['date_timestamp'] . "'>
                                " . ($delete ? "<button name='rm_appointment' value='' style='background:red; color:white'>&#10008;</button>" : "") .
                        "</form>
                        </td>
                        </tr>\n";
                    $c++;
                }
            }
        }
    } else {
        echo "<script>location.href='" . $db->url() . "appointments.php'</script>";
    }


    $dbh = null;
    ?>

    <script>
        function prompt(date, client) {
            document.getElementById("popup").style.display = "block";
            document.getElementById("date").innerHTML = date;
            document.getElementById("add_timestamp").value = date;
            document.getElementById("doctor").innerHTML = "<input form='add' list='doctors" + parseInt(date.slice(11, 13)) + "' name='add_doctor' id='add_doctor' required style='width:200px'>";
            if (client.length >= 9) {
                document.getElementById("client").style.display = 'none';
                document.getElementById("add_client").value = client;
            }
        }
    </script>

    <div id="popup">
        <div style="float:right"><button onclick="document.getElementById('popup').style.display = 'none';">X</button></div><br><br>
        <div>
            <form action='' method='post' id='add'>
                <h2 id="date"></h2>
                <input name="add_timestamp" id="add_timestamp" style="display:none">
                <span id="client">
                    <label for="add_client">Client: </label>
                    <input list='clients' name='add_client' id='add_client' style="width:200px" required>
                </span>
                <label for="add_doctor">Doctor: </label>
                <span id="doctor"></span>
                <br>
                <br>
                <label for="add_description">Description: </label>
                <textarea maxlength="255" rows='4' wrap='hard' name='add_description' id="add_description" required></textarea><br>
                <br>
                <div style="width: 100%; text-align: center">
                    <button name='' value='test'>SUBMIT</button>
                </div>
            </form>
        </div>
    </div>
    <div style="left:0;width:100%;height:20px;position:fixed;z-index:99;bottom:0;text-align:center">
        <span style="background:rgba(150,150,150,0.5)">SIBD - Project Part 3 - Group 50</span>
    </div>
</body>

</html>