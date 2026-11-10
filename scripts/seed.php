<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Solo CLI'); }
require dirname(__DIR__).'/app/bootstrap.php';
$users=[
 ['Auditor Demo','auditor@local.test','auditor'],
 ['Cliente Demo','cliente@local.test','client'],
 ['Administrador Demo','admin@local.test','admin'],
];
$password=bin2hex(random_bytes(9));
$db=db();
$db->beginTransaction();
try {
    $project=$db->prepare('INSERT INTO projects(name,description) VALUES(?,?)');
    $project->execute(['Auditoría Demo FY26','Proyecto demostrativo para Xchange Portal']);
    $pid=(int)$db->lastInsertId();
    foreach ($users as [$name,$email,$role]) {
        $s=$db->prepare('INSERT INTO users(name,email,password_hash) VALUES(?,?,?)');
        $s->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
        $id=(int)$db->lastInsertId();
        $db->prepare('INSERT INTO project_members(project_id,user_id,role) VALUES(?,?,?)')->execute([$pid,$id,$role]);
    }
    $db->commit();
} catch (Throwable $e) { $db->rollBack(); throw $e; }
echo "Datos demo instalados. Proyecto #$pid\n";
echo "Usuarios: auditor@local.test, cliente@local.test, admin@local.test\n";
echo "Contraseña temporal común: $password\n";
echo "Cambia las credenciales tras las pruebas. No ejecutes este script en producción.\n";
