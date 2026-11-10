<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$action=(string)($_GET['action']??'');
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    checkCsrf();
    $postAction=(string)($_POST['action']??'');
    try {
        if ($postAction==='login') {
            $q=db()->prepare('SELECT * FROM users WHERE email=? AND is_active=1');
            $q->execute([trim((string)($_POST['email']??''))]);
            $user=$q->fetch();
            if (!$user || !password_verify((string)($_POST['password']??''),$user['password_hash'])) throw new RuntimeException('Credenciales incorrectas.');
            session_regenerate_id(true);
            $_SESSION['uid']=(int)$user['id'];
            go();
        }
        if ($postAction==='logout') {
            $_SESSION=[]; session_regenerate_id(true); go();
        }
        $actor=requireUser(); $uid=(int)$actor['id'];
        if ($postAction==='create') {
            $project=(int)($_POST['project_id']??0);
            $role=membership($project,$uid)['role']??null;
            if (!in_array($role,['auditor','admin'],true)) throw new RuntimeException('No autorizado');
            $title=trim((string)($_POST['title']??''));
            if ($title==='' || mb_strlen($title)>240) throw new RuntimeException('Título inválido');
            $priority=(string)($_POST['priority']??'normal');
            if (!in_array($priority,['normal','high','critical'],true)) throw new RuntimeException('Prioridad inválida');
            $due=(string)($_POST['due_date']??'');
            if ($due!=='' && (!DateTimeImmutable::createFromFormat('!Y-m-d',$due) || DateTimeImmutable::createFromFormat('!Y-m-d',$due)->format('Y-m-d')!==$due)) throw new RuntimeException('Fecha inválida');
            $assigned=(int)($_POST['assigned_to']??0);
            if ($assigned && !membership($project,$assigned)) throw new RuntimeException('Asignado no pertenece al proyecto');
            $q=db()->prepare('INSERT INTO requests(project_id,title,description,priority,due_date,created_by,assigned_to) VALUES(?,?,?,?,?,?,?)');
            $q->execute([$project,$title,trim((string)($_POST['description']??'')),$priority,$due?:null,$uid,$assigned?:null]);
            $rid=(int)db()->lastInsertId();
            recordEvent($project,$rid,$uid,'request_created',$title);
            go('?request='.$rid);
        }
        $rid=(int)($_POST['request_id']??0);
        $req=getRequest($rid,$uid);
        $role=membership((int)$req['project_id'],$uid)['role'];
        if ($postAction==='comment') {
            $body=trim((string)($_POST['body']??''));
            if ($body==='' || mb_strlen($body)>5000) throw new RuntimeException('Comentario inválido');
            db()->prepare('INSERT INTO request_comments(request_id,user_id,body) VALUES(?,?,?)')->execute([$rid,$uid,$body]);
            recordEvent((int)$req['project_id'],$rid,$uid,'comment_added');
            go('?request='.$rid);
        }
        if ($postAction==='status') {
            if (!in_array($role,['auditor','admin'],true)) throw new RuntimeException('Sin permisos de revisión');
            $status=(string)($_POST['status']??'');
            $allowed=['submitted'=>['accepted','returned'],'returned'=>[],'open'=>[],'accepted'=>[]];
            if (!in_array($status,$allowed[$req['status']]??[],true)) throw new RuntimeException('Transición de estado inválida');
            db()->prepare('UPDATE requests SET status=? WHERE id=?')->execute([$status,$rid]);
            recordEvent((int)$req['project_id'],$rid,$uid,'status_changed',$req['status'].' → '.$status);
            go('?request='.$rid);
        }
        if ($postAction==='upload') {
            if (!isset($_FILES['document']) || !is_array($_FILES['document'])) throw new RuntimeException('Archivo requerido');
            $f=$_FILES['document'];
            if ($f['error']!==UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) throw new RuntimeException('Falló la carga o se excedió el límite de PHP');
            global $config;
            $max=(int)$config['max_upload_bytes'];
            if ((int)$f['size']<=0 || (int)$f['size']>$max) throw new RuntimeException('Archivo vacío o superior a 20MB');
            $original=basename(str_replace('\\','/',(string)$f['name']));
            $original=preg_replace('/[\x00-\x1f\x7f]/u','',$original)?:'documento';
            if (strlen($original)>255) throw new RuntimeException('Nombre muy largo');
            $ext=strtolower(pathinfo($original,PATHINFO_EXTENSION));
            $allowed=[
                'pdf'=>['application/pdf'],'png'=>['image/png'],'jpg'=>['image/jpeg'],'jpeg'=>['image/jpeg'],
                'txt'=>['text/plain'],'csv'=>['text/plain','text/csv','application/csv','application/vnd.ms-excel'],
                'docx'=>['application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/zip'],
                'xlsx'=>['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/zip'],
                'zip'=>['application/zip','application/x-zip-compressed']
            ];
            $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
            if (!isset($allowed[$ext]) || !in_array($mime,$allowed[$ext],true)) throw new RuntimeException('Tipo de archivo no permitido');
            $dir=$config['storage_path'];
            if (!is_dir($dir) && !mkdir($dir,0700,true) && !is_dir($dir)) throw new RuntimeException('Storage no disponible');
            $name=bin2hex(random_bytes(24)).'.bin';
            $target=$dir.'/'.$name;
            if (!move_uploaded_file($f['tmp_name'],$target)) throw new RuntimeException('No se pudo almacenar archivo');
            try {
                db()->beginTransaction();
                db()->prepare('INSERT INTO documents(request_id,uploaded_by,original_name,storage_name,mime_type,byte_size,sha256) VALUES(?,?,?,?,?,?,?)')
                  ->execute([$rid,$uid,$original,$name,$mime,(int)$f['size'],hash_file('sha256',$target)]);
                if (in_array($req['status'],['open','returned'],true)) db()->prepare("UPDATE requests SET status='submitted' WHERE id=?")->execute([$rid]);
                recordEvent((int)$req['project_id'],$rid,$uid,'document_uploaded',$original);
                db()->commit();
            } catch (Throwable $ex) { if (db()->inTransaction()) db()->rollBack(); @unlink($target); throw $ex; }
            go('?request='.$rid);
        }
        throw new RuntimeException('Acción desconocida');
    } catch (Throwable $ex) {
        $error=$ex instanceof PDOException ? 'Error de base de datos. Revisa configuración.' : $ex->getMessage();
    }
}
if ($action==='download') {
    $user=requireUser(); $id=(int)($_GET['file']??0);
    $q=db()->prepare('SELECT d.*,r.project_id FROM documents d JOIN requests r ON r.id=d.request_id JOIN project_members pm ON pm.project_id=r.project_id AND pm.user_id=? WHERE d.id=?');
    $q->execute([(int)$user['id'],$id]); $file=$q->fetch();
    if (!$file) { http_response_code(404); exit('Documento no encontrado'); }
    $storage=$config['storage_path'].'/'.$file['storage_name'];
    if (!is_file($storage)) { http_response_code(404); exit('Archivo no disponible'); }
    recordEvent((int)$file['project_id'],(int)$file['request_id'],(int)$user['id'],'document_downloaded',(string)$file['original_name']);
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="download"; filename*=UTF-8\'\''.rawurlencode($file['original_name']));
    header('Content-Length: '.filesize($storage));
    header('Cache-Control: private, no-store');
    readfile($storage); exit;
}
$me=me();
if ($me) {
    $uid=(int)$me['id'];
    $q=db()->prepare('SELECT p.*,pm.role FROM projects p JOIN project_members pm ON pm.project_id=p.id WHERE pm.user_id=? ORDER BY p.name');
    $q->execute([$uid]); $projects=$q->fetchAll();
    $projectId=(int)($_GET['project']??($projects[0]['id']??0));
    if ($projectId && !membership($projectId,$uid)) { http_response_code(403); exit('No autorizado'); }
    $role=membership($projectId,$uid)['role']??'client';
    $q=db()->prepare('SELECT r.*,u.name AS assigned_name FROM requests r JOIN project_members pm ON pm.project_id=r.project_id AND pm.user_id=? WHERE r.project_id=? ORDER BY r.created_at DESC,r.id DESC');
    $q->execute([$uid,$projectId]); $requests=$q->fetchAll();
    $selected=isset($_GET['request'])?getRequest((int)$_GET['request'],$uid):null;
    if ($selected) {
        $projectId=(int)$selected['project_id'];
        $role=membership($projectId,$uid)['role'];
        $q=db()->prepare('SELECT d.*,u.name AS author_name FROM documents d JOIN users u ON u.id=d.uploaded_by WHERE d.request_id=? ORDER BY d.id DESC');
        $q->execute([$selected['id']]);$documents=$q->fetchAll();
        $q=db()->prepare('SELECT c.*,u.name AS author_name FROM request_comments c JOIN users u ON u.id=c.user_id WHERE c.request_id=? ORDER BY c.id');
        $q->execute([$selected['id']]);$comments=$q->fetchAll();
        $q=db()->prepare('SELECT a.*,u.name AS author_name FROM audit_events a JOIN users u ON u.id=a.user_id WHERE a.request_id=? ORDER BY a.id DESC LIMIT 100');
        $q->execute([$selected['id']]);$events=$q->fetchAll();
    }
    $q=db()->prepare('SELECT u.id,u.name FROM users u JOIN project_members pm ON pm.user_id=u.id WHERE pm.project_id=? ORDER BY u.name');
    $q->execute([$projectId]);$members=$q->fetchAll();
    $counts=['total'=>count($requests),'open'=>0,'submitted'=>0,'accepted'=>0,'overdue'=>0];
    foreach($requests as $r) {
        if (isset($counts[$r['status']])) $counts[$r['status']]++;
        if ($r['due_date'] && $r['due_date']<date('Y-m-d') && $r['status']!=='accepted') $counts['overdue']++;
    }
}
?><!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Xchange Portal</title><link rel="stylesheet" href="assets/app.css"><script src="assets/app.js" defer></script></head><body>
<?php if (!$me): ?>
<main class="login"><form class="panel" method="post"><div class="brand"><b>✦</b> Xchange Portal</div><h1>Bienvenido</h1><p class="muted">Centro de colaboración para auditorías y documentos.</p>
<?php if($error):?><p class="message"><?=h($error)?></p><?php endif;?>
<input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="login">
<div class="field"><label for="email">Correo electrónico</label><input id="email" name="email" type="email" required autocomplete="username"></div>
<div class="field"><label for="password">Contraseña</label><input id="password" name="password" type="password" required autocomplete="current-password"></div>
<button type="submit">Iniciar sesión</button><p class="small muted" style="margin-top:20px">Entorno local · PHP / SQL · sin dependencias externas</p></form></main>
<?php else: ?>
<div class="app"><aside class="side"><div class="brand"><b>✦</b> Xchange</div><nav><a href="index.php" class="active">◫ &nbsp; Solicitudes</a><a href="index.php#new-request">＋ &nbsp; Nueva solicitud</a><a href="index.php#team">♙ &nbsp; Equipo</a></nav><p class="small muted" style="margin-top:35px">Portal seguro de intercambio de archivos</p></aside>
<main class="main"><header class="top"><div><p class="eyebrow">WORKSPACE / AUDITORÍA</p><h1><?=h($selected?$selected['title']:'Centro de solicitudes')?></h1><p class="muted"><?=h($selected?$selected['project_name']:($projects[0]['name']??'Sin proyectos asignados'))?></p></div><div class="topuser"><?=h($me['name'])?> <form method="post" style="display:inline-block;margin-left:12px"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="logout"><button class="secondary">Salir</button></form></div></header>
<?php if($error):?><p class="message"><?=h($error)?></p><?php endif;?>
<?php if($selected):?>
<p><a href="index.php?project=<?=$projectId?>">← Volver a solicitudes</a></p>
<div class="detail"><section class="stack"><div class="panel"><h2>Detalles de solicitud #<?=$selected['id']?></h2><p><?=nl2br(h($selected['description']))?></p><p><span class="pill <?=h($selected['status'])?>"><?=h($selected['status'])?></span> · Prioridad: <?=h($selected['priority'])?> · Fecha límite: <?=h($selected['due_date']??'Sin fecha')?></p><p class="muted">Asignado a: <?=h($selected['assigned_name']??'Sin asignar')?></p>
<?php if($selected['status']==='submitted' && in_array($role,['auditor','admin'],true)):?>
<form method="post" class="actions" data-confirm="¿Registrar revisión de esta solicitud?"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="status"><input type="hidden" name="request_id" value="<?=$selected['id']?>"><button name="status" value="accepted">Aceptar</button><button class="secondary" name="status" value="returned">Devolver</button></form><?php endif;?></div>
<div class="panel"><h2>Documentos compartidos</h2><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="upload"><input type="hidden" name="request_id" value="<?=$selected['id']?>"><div class="field"><label for="document">Subir PDF, Office, imágenes, ZIP, TXT o CSV (máx. 20MB)</label><input id="document" name="document" type="file" required></div><button>Subir documento</button></form>
<?php foreach($documents as $d):?><div class="file"><div><strong><?=h($d['original_name'])?></strong><div class="small muted"><?=h($d['author_name'])?> · <?=h($d['created_at'])?> · <?=number_format((int)$d['byte_size']/1024,1)?> KB</div></div><a href="?action=download&amp;file=<?=$d['id']?>">Descargar</a></div><?php endforeach;?>
<?php if(!$documents):?><p class="muted" style="margin-top:18px">No hay documentos compartidos todavía.</p><?php endif;?></div></section>
<section class="stack"><div class="panel"><h2>Comentarios</h2><?php foreach($comments as $c):?><div class="file"><div><strong><?=h($c['author_name'])?></strong> <span class="small muted"><?=h($c['created_at'])?></span><p><?=nl2br(h($c['body']))?></p></div></div><?php endforeach;?><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="comment"><input type="hidden" name="request_id" value="<?=$selected['id']?>"><div class="field"><label for="body">Nuevo comentario</label><textarea id="body" name="body" maxlength="5000" required></textarea></div><button>Publicar comentario</button></form></div><div class="panel"><h2>Actividad reciente</h2><?php foreach($events as $e):?><p class="small"><b><?=h($e['author_name'])?></b> · <?=h($e['action'])?><br><span class="muted"><?=h($e['created_at'])?> <?=h($e['details'])?></span></p><?php endforeach;?></div></section></div>
<?php else:?>
<div class="grid"><div class="panel"><div class="metric"><?=$counts['total']?></div><div class="metric-caption">Solicitudes totales</div></div><div class="panel"><div class="metric"><?=$counts['open']?></div><div class="metric-caption">Pendientes</div></div><div class="panel"><div class="metric"><?=$counts['submitted']?></div><div class="metric-caption">Recibidas</div></div><div class="panel"><div class="metric"><?=$counts['overdue']?></div><div class="metric-caption danger">Vencidas</div></div></div>
<section class="panel"><div class="toolbar"><div><h2>Document requests</h2><p class="muted">Intercambio, seguimiento y revisión de evidencia.</p></div><input id="filter-text" type="search" placeholder="Buscar solicitudes…" aria-label="Buscar solicitudes"></div>
<div class="table-wrap"><table><thead><tr><th>#</th><th>Solicitud</th><th>Asignado</th><th>Estado</th><th>Prioridad</th><th>Fecha límite</th></tr></thead><tbody><?php foreach($requests as $r):?><tr data-request-row><td><?=h((string)$r['id'])?></td><td><a href="?request=<?=$r['id']?>"><?=h($r['title'])?></a></td><td><?=h($r['assigned_name']??'—')?></td><td><span class="pill <?=h($r['status'])?>"><?=h($r['status'])?></span></td><td><?=h($r['priority'])?></td><td><?=h($r['due_date']??'—')?></td></tr><?php endforeach;?></tbody></table><?php if(!$requests):?><p class="muted">Todavía no hay solicitudes en este proyecto.</p><?php endif;?></div></section>
<?php if(in_array($role,['auditor','admin'],true)):?><section class="panel" id="new-request" style="margin-top:20px"><h2>Nueva solicitud</h2><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="create"><input type="hidden" name="project_id" value="<?=$projectId?>"><div class="detail"><div><div class="field"><label>Título</label><input name="title" maxlength="240" required></div><div class="field"><label>Descripción</label><textarea name="description"></textarea></div></div><div><div class="field"><label>Responsable</label><select name="assigned_to"><option value="">Sin asignar</option><?php foreach($members as $m):?><option value="<?=$m['id']?>"><?=h($m['name'])?></option><?php endforeach;?></select></div><div class="field"><label>Prioridad</label><select name="priority"><option value="normal">Normal</option><option value="high">Alta</option><option value="critical">Crítica</option></select></div><div class="field"><label>Fecha límite</label><input name="due_date" type="date"></div></div></div><button>Crear solicitud</button></form></section><?php endif;?>
<section class="panel" id="team" style="margin-top:20px"><h2>Miembros del proyecto</h2><?php foreach($members as $m):?><p><?=h($m['name'])?></p><?php endforeach;?></section>
<?php endif;?></main></div><?php endif;?></body></html>
