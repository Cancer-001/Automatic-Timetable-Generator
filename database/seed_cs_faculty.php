<?php
/**
 * Seed CS Faculty — 36 members from facultycs.xlsx
 * Run: php database/seed_cs_faculty.php
 * Or visit: http://localhost/assigmentupdated/project/database/seed_cs_faculty.php
 */
require_once dirname(__DIR__) . '/config/db.php';

// Ensure CS department exists
$conn->query("INSERT IGNORE INTO department (name, code) VALUES ('Computer Science', 'CS')");
$deptRes = $conn->query("SELECT id FROM department WHERE code = 'CS' LIMIT 1");
$csId = $deptRes ? (int)$deptRes->fetch_assoc()['id'] : null;

$pass = password_hash('faculty123', PASSWORD_DEFAULT);
$stmt = $conn->prepare('INSERT IGNORE INTO faculty (email, password_hash, full_name, department_id, is_active) VALUES (?, ?, ?, ?, 1)');

$faculty = [
    ['dr.ansar.munir@cs.edu.pk', 'Dr Ansar Munir'],  // Regular
    ['imran.ali@cs.edu.pk', 'Imran Ali'],  // Regular
    ['nasir.hussain@cs.edu.pk', 'Nasir Hussain'],  // Regular
    ['aqsa.altaf@cs.edu.pk', 'Aqsa Altaf'],  // Visiting
    ['zeeshan.ali@cs.edu.pk', 'Zeeshan Ali'],  // Regular
    ['arfa.tariq@cs.edu.pk', 'Arfa Tariq'],  // Visiting
    ['fizzah.ishtiaq@cs.edu.pk', 'Fizzah Ishtiaq'],  // Regular
    ['bisma.imran@cs.edu.pk', 'Bisma Imran'],  // Regular
    ['ahmad.hamza@cs.edu.pk', 'Ahmad Hamza'],  // Visiting
    ['dr.muhammad.moavia@cs.edu.pk', 'Dr. Muhammad Moavia'],  // Regular
    ['namra.shamin@cs.edu.pk', 'Namra Shamin'],  // Regular
    ['hadia.rehan@cs.edu.pk', 'Hadia Rehan'],  // Regular
    ['kainat.sajjad@cs.edu.pk', 'Kainat Sajjad'],  // Regular
    ['sundas.fida.hussain@cs.edu.pk', 'Sundas Fida Hussain'],  // Visiting
    ['mohsin.raza@cs.edu.pk', 'Mohsin Raza'],  // Visiting
    ['arbab.khan@cs.edu.pk', 'Arbab Khan'],  // Regular
    ['sonia.jamil@cs.edu.pk', 'Sonia Jamil'],  // Regular
    ['abdul.basit@cs.edu.pk', 'Abdul Basit'],  // Visiting
    ['majid.khawar@cs.edu.pk', 'Majid Khawar'],  // Regular
    ['dr.zia.ur.rehman@cs.edu.pk', 'Dr. Zia ur Rehman'],  // Regular
    ['zohair.haider@cs.edu.pk', 'Zohair Haider'],  // Regular
    ['sana.fatima@cs.edu.pk', 'Sana Fatima'],  // Visiting
    ['shakab.ahmad@cs.edu.pk', 'Shakab Ahmad'],  // Regular
    ['qasim.niaz@cs.edu.pk', 'Qasim Niaz'],  // Regular
    ['dr.abdullah.shah@cs.edu.pk', 'Dr. Abdullah Shah'],  // Visiting
    ['ans.khalid@cs.edu.pk', 'Ans Khalid'],  // Regular
    ['saleh.rehman@cs.edu.pk', 'Saleh Rehman'],  // Visiting
    ['parveen.bano@cs.edu.pk', 'Parveen Bano'],  // Regular
    ['muhammad.arslan@cs.edu.pk', 'Muhammad Arslan'],  // Visiting
    ['qandeel.asghar@cs.edu.pk', 'Qandeel Asghar'],  // Regular
    ['muhammad.abrar@cs.edu.pk', 'Muhammad Abrar'],  // Regular
    ['dr.mishal@cs.edu.pk', 'Dr. Mishal'],  // Visiting
    ['allah.rakha@cs.edu.pk', 'Allah Rakha'],  // Visiting
    ['hafiz.muhammad.ejaz@cs.edu.pk', 'Hafiz Muhammad Ejaz'],  // Regular
    ['safia.sultana@cs.edu.pk', 'Safia Sultana'],  // Visiting
    ['azka.fatima@cs.edu.pk', 'Azka Fatima'],  // Visiting
];

$inserted = 0; $skipped = 0;
foreach ($faculty as $f) {
    $stmt->bind_param('sssi', $f[0], $pass, $f[1], $csId);
    $stmt->execute();
    if ($stmt->affected_rows > 0) $inserted++;
    else $skipped++;
}

echo "<b>CS Faculty Seeded</b><br>";
echo "Inserted: $inserted new faculty<br>";
echo "Skipped (already exist): $skipped<br>";
echo "<br>Default password for all: <b>faculty123</b><br>";
echo "<br>Faculty list:<br><ul>";
foreach ($faculty as $f) {
    echo "<li>" . htmlspecialchars($f[1]) . " — " . htmlspecialchars($f[0]) . "</li>";
}
echo "</ul>";
echo '<a href="../auth/login.php">Go to Login</a>';