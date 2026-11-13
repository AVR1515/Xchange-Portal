<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$action=(string)($_GET['action']??'');
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $postLimit=ini_parse_quantity((string)ini_get('post_max_size'));
    if ($postLimit>0 && (int)($_SERVER['CONTENT_LENGTH']??0)>$postLimit) {
        http_response_code(413);
        exit('La entrega excede el tamaño permitido por el servidor. Vuelve a la solicitud e intenta con un archivo más pequeño.');
    }
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
        if ($postAction==='edit_member_role' || $postAction==='remove_member') {
            $project=(int)($_POST['project_id']??0);
            $member=(int)($_POST['member_id']??0);
            $memberRole=(string)($_POST['member_role']??'');
            if ((membership($project,$uid)['role']??null)!=='admin') {
                throw new RuntimeException('Sin permisos para administrar miembros');
            }
            $removing=$postAction==='remove_member';
            if (!$removing && !in_array($memberRole,['auditor','client'],true)) {
                throw new RuntimeException('Rol inválido');
            }
            $projectTransaction=db();
            $projectTransaction->beginTransaction();
            if ($removing) {
                $delete=db()->prepare("DELETE FROM project_members WHERE project_id=? AND user_id=? AND role IN ('auditor','client')");
                $delete->execute([$project,$member]);
                if ($delete->rowCount()!==1) throw new RuntimeException('No se pudo retirar al miembro del proyecto');
                db()->prepare('UPDATE requests SET assigned_to=NULL WHERE project_id=? AND assigned_to=?')->execute([$project,$member]);
                recordEvent($project,null,$uid,'project_member_removed',(string)$member);
            } else {
                $update=db()->prepare("UPDATE project_members SET role=? WHERE project_id=? AND user_id=? AND role IN ('auditor','client') AND role<>?");
                $update->execute([$memberRole,$project,$member,$memberRole]);
                if ($update->rowCount()===1) {
                    recordEvent($project,null,$uid,'project_member_role_changed',$member.' → '.$memberRole);
                }
            }
            $projectTransaction->commit();
            go('?project='.$project.'#team');
        }
        if ($postAction==='add_member') {
            $project=(int)($_POST['project_id']??0);
            if ((membership($project,$uid)['role']??null)!=='admin') {
                throw new RuntimeException('Sin permisos para administrar miembros');
            }
            $email=trim((string)($_POST['email']??''));
            $name=trim((string)($_POST['name']??''));
            $password=(string)($_POST['password']??'');
            $memberRole=(string)($_POST['member_role']??'');
            if (!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>190 || !in_array($memberRole,['auditor','client'],true)) {
                throw new RuntimeException('Correo o rol inválido');
            }
            $projectTransaction=db();
            $projectTransaction->beginTransaction();
            $query=db()->prepare('SELECT id FROM users WHERE email=? AND is_active=1');
            $query->execute([$email]);
            $memberId=$query->fetchColumn();
            if (!$memberId) {
                if ($name==='' || mb_strlen($name)>150 || strlen($password)<12 || strlen($password)>72 || str_contains($password,chr(0))) {
                    throw new RuntimeException('Para una cuenta nueva indica el nombre y una contraseña de 12 a 72 bytes');
                }
                db()->prepare('INSERT INTO users(name,email,password_hash) VALUES(?,?,?)')
                    ->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
                $memberId=db()->lastInsertId();
            }
            if (membership($project,(int)$memberId)) {
                throw new RuntimeException('Esta cuenta ya pertenece al proyecto');
            }
            db()->prepare('INSERT INTO project_members(project_id,user_id,role) VALUES(?,?,?)')->execute([$project,$memberId,$memberRole]);
            recordEvent($project,null,$uid,'project_member_added',(string)$memberId);
            $projectTransaction->commit();
            go('?project='.$project.'#team');
        }
        if ($postAction==='create_project' || $postAction==='edit_project') {
            $project=(int)($_POST['project_id']??0);
            $creating=$postAction==='create_project';
            if ($creating ? !canCreateProject($uid) : (membership($project,$uid)['role']??null)!=='admin') {
                throw new RuntimeException('Sin permisos para administrar este proyecto');
            }
            $name=trim((string)($_POST['name']??''));
            $description=trim((string)($_POST['description']??''));
            if ($name==='' || mb_strlen($name)>200 || strlen($description)>65535) {
                throw new RuntimeException('Nombre o descripción del proyecto inválidos');
            }
            $projectTransaction=db();
            $projectTransaction->beginTransaction();
            if ($creating) {
                db()->prepare('INSERT INTO projects(name,description) VALUES(?,?)')->execute([$name,$description]);
                $project=(int)db()->lastInsertId();
                db()->prepare("INSERT INTO project_members(project_id,user_id,role) VALUES(?,?,'admin')")->execute([$project,$uid]);
            } else {
                db()->prepare('UPDATE projects SET name=?,description=? WHERE id=?')->execute([$name,$description,$project]);
            }
            recordEvent($project,null,$uid,$creating?'project_created':'project_updated',$name);
            db()->commit();
            go('?project='.$project.'#projects');
        }
        if ($postAction==='create' || $postAction==='edit_request') {
            $editing=$postAction==='edit_request';
            $existing=$editing?getRequest((int)($_POST['request_id']??0),$uid):null;
            $project=(int)($existing['project_id']??$_POST['project_id']??0);
            $role=membership($project,$uid)['role']??null;
            if (!in_array($role,['auditor','admin'],true)) throw new RuntimeException('No autorizado');
            $title=trim((string)($_POST['title']??''));
            if ($title==='' || mb_strlen($title)>240) throw new RuntimeException('Título inválido');
            $priority=(string)($_POST['priority']??'normal');
            if (!in_array($priority,['normal','high','critical'],true)) throw new RuntimeException('Prioridad inválida');
            $due=(string)($_POST['due_date']??'');
            if ($due!=='' && (!DateTimeImmutable::createFromFormat('!Y-m-d',$due) || DateTimeImmutable::createFromFormat('!Y-m-d',$due)->format('Y-m-d')!==$due)) throw new RuntimeException('Fecha inválida');
            $assigned=(int)($_POST['assigned_to']??0);
            $description=trim((string)($_POST['description']??''));
            $projectTransaction=db();
            $projectTransaction->beginTransaction();
            if ($assigned) {
                $assignment=db()->prepare('SELECT 1 FROM project_members WHERE project_id=? AND user_id=? FOR UPDATE');
                $assignment->execute([$project,$assigned]);
                if (!$assignment->fetchColumn()) throw new RuntimeException('Asignado no pertenece al proyecto');
            }
            if ($editing) {
                $rid=(int)$existing['id'];
                $q=db()->prepare('UPDATE requests SET title=?,description=?,priority=?,due_date=?,assigned_to=? WHERE id=?');
                $q->execute([$title,$description,$priority,$due?:null,$assigned?:null,$rid]);
            } else {
                $q=db()->prepare('INSERT INTO requests(project_id,title,description,priority,due_date,created_by,assigned_to) VALUES(?,?,?,?,?,?,?)');
                $q->execute([$project,$title,$description,$priority,$due?:null,$uid,$assigned?:null]);
                $rid=(int)db()->lastInsertId();
            }
            recordEvent($project,$rid,$uid,$editing?'request_updated':'request_created',$title);
            $projectTransaction->commit();
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
            $reason=trim((string)($_POST['reason']??''));
            if (($status==='returned' && $reason==='') || mb_strlen($reason)>5000) {
                throw new RuntimeException('Indica un motivo de devolución de hasta 5000 caracteres');
            }
            $projectTransaction=db();
            $projectTransaction->beginTransaction();
            $update=db()->prepare('UPDATE requests SET status=? WHERE id=? AND status=?');
            $update->execute([$status,$rid,$req['status']]);
            if ($update->rowCount()!==1) throw new RuntimeException('El estado cambió. Actualiza la página y vuelve a revisar la solicitud.');
            if ($reason!=='') {
                db()->prepare('INSERT INTO request_comments(request_id,user_id,body) VALUES(?,?,?)')->execute([$rid,$uid,$reason]);
            }
            recordEvent((int)$req['project_id'],$rid,$uid,'status_changed',$req['status'].' → '.$status);
            $projectTransaction->commit();
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
                db()->prepare("UPDATE requests SET status='submitted' WHERE id=? AND status IN ('open','returned')")->execute([$rid]);
                recordEvent((int)$req['project_id'],$rid,$uid,'document_uploaded',$original);
                db()->commit();
            } catch (Throwable $ex) { if (db()->inTransaction()) db()->rollBack(); @unlink($target); throw $ex; }
            go('?request='.$rid);
        }
        throw new RuntimeException('Acción desconocida');
    } catch (Throwable $ex) {
        if (isset($projectTransaction) && $projectTransaction->inTransaction()) $projectTransaction->rollBack();
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
    if (!hash_equals($file['sha256'],hash_file('sha256',$storage) ?: '')) {
        http_response_code(409);
        exit('El archivo no supera la verificación de integridad');
    }
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
    $selected=isset($_GET['request'])?getRequest((int)$_GET['request'],$uid):null;
    $projectId=(int)($selected['project_id']??$_GET['project']??($projects[0]['id']??0));
    if ($projectId && !membership($projectId,$uid)) { http_response_code(403); exit('No autorizado'); }
    $role=membership($projectId,$uid)['role']??'client';
    $activeProject=null;
    foreach ($projects as $availableProject) {
        if ((int)$availableProject['id']===$projectId) {
            $activeProject=$availableProject;
            break;
        }
    }
    $canCreateProject=canCreateProject($uid);
    $q=db()->prepare("SELECT a.action,a.created_at,r.id,r.title FROM audit_events a JOIN requests r ON r.id=a.request_id AND r.project_id=a.project_id WHERE a.project_id=? AND a.user_id<>? AND a.action IN ('request_created','request_updated','document_uploaded','status_changed','comment_added') ORDER BY a.id DESC LIMIT 15");
    $q->execute([$projectId,$uid]);
    $updates=$q->fetchAll();
    $projectHistory=[];
    if ($role==='admin') {
        $q=db()->prepare('SELECT a.action,a.details,a.created_at,u.name FROM audit_events a JOIN users u ON u.id=a.user_id WHERE a.project_id=? AND a.request_id IS NULL ORDER BY a.id DESC LIMIT 25');
        $q->execute([$projectId]);
        $projectHistory=$q->fetchAll();
    }
    $q=db()->prepare('SELECT r.*,u.name AS assigned_name FROM requests r JOIN project_members pm ON pm.project_id=r.project_id AND pm.user_id=? LEFT JOIN users u ON u.id=r.assigned_to WHERE r.project_id=? ORDER BY r.created_at DESC,r.id DESC');
    $q->execute([$uid,$projectId]); $requests=$q->fetchAll();
    if ($selected) {
        $q=db()->prepare('SELECT d.*,u.name AS author_name FROM documents d JOIN users u ON u.id=d.uploaded_by WHERE d.request_id=? ORDER BY d.id DESC');
        $q->execute([$selected['id']]);$documents=$q->fetchAll();
        $versionCounts=[];
        foreach (array_reverse(array_keys($documents)) as $index) {
            $name=$documents[$index]['original_name'];
            $versionCounts[$name]=($versionCounts[$name]??0)+1;
            $documents[$index]['version']=$versionCounts[$name];
        }
        $q=db()->prepare('SELECT c.*,u.name AS author_name FROM request_comments c JOIN users u ON u.id=c.user_id WHERE c.request_id=? ORDER BY c.id');
        $q->execute([$selected['id']]);$comments=$q->fetchAll();
        $q=db()->prepare('SELECT a.*,u.name AS author_name FROM audit_events a JOIN users u ON u.id=a.user_id WHERE a.request_id=? ORDER BY a.id DESC LIMIT 100');
        $q->execute([$selected['id']]);$events=$q->fetchAll();
    }
    $q=db()->prepare('SELECT u.id,u.name,pm.role FROM users u JOIN project_members pm ON pm.user_id=u.id WHERE pm.project_id=? ORDER BY u.name');
    $q->execute([$projectId]);$members=$q->fetchAll();
    $statusLabels=['open'=>'Pendiente','submitted'=>'Recibida','returned'=>'Devuelta','accepted'=>'Aceptada'];
    $priorityLabels=['normal'=>'Normal','high'=>'Alta','critical'=>'Crítica'];
    $counts=['total'=>count($requests),'open'=>0,'submitted'=>0,'accepted'=>0,'overdue'=>0];
    $today=date('Y-m-d');
    $nextWeek=date('Y-m-d',strtotime('+7 days'));
    $deadlines=[];
    foreach($requests as $r) {
        if (isset($counts[$r['status']])) $counts[$r['status']]++;
        if ($r['due_date'] && $r['status']!=='accepted') {
            if ($r['due_date']<$today) $counts['overdue']++;
            if ($r['due_date']<=$nextWeek) $deadlines[]=$r;
        }
    }
    usort($deadlines,static fn(array $left,array $right): int => $left['due_date']<=>$right['due_date']);
    $filters=[
        'q'=>trim((string)($_GET['q']??'')),
        'status'=>(string)($_GET['status']??''),
        'priority'=>(string)($_GET['priority']??''),
        'due'=>(string)($_GET['due']??''),
    ];
    $dueLabels=['overdue'=>'Vencidas','upcoming'=>'Próximos 7 días','none'=>'Sin fecha'];
    $filtered=array_values(array_filter($requests,static function (array $request) use ($filters,$today,$nextWeek): bool {
        $due=$request['due_date'];
        $isOpen=$request['status']!=='accepted';
        $matchesDue=match($filters['due']) {
            'overdue'=>$isOpen && $due!==null && $due<$today,
            'upcoming'=>$isOpen && $due!==null && $due>=$today && $due<=$nextWeek,
            'none'=>$due===null,
            default=>true,
        };
        return ($filters['status']==='' || $filters['status']===$request['status'])
            && ($filters['priority']==='' || $filters['priority']===$request['priority'])
            && $matchesDue
            && ($filters['q']==='' || mb_stripos($request['title'].' '.$request['assigned_name'], $filters['q'])!==false);
    }));
    $listQuery=['project'=>$projectId]+$filters;
    if ($action==='export_requests' && $projectId) {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="solicitudes.csv"');
        $output=fopen('php://output','wb');
        fwrite($output,"\xEF\xBB\xBF");
        fputcsv($output,['ID','Solicitud','Responsable','Estado','Prioridad','Fecha límite'],',','"','');
        foreach ($filtered as $r) {
            $row=[$r['id'],$r['title'],$r['assigned_name'],$statusLabels[$r['status']],$priorityLabels[$r['priority']],$r['due_date']];
            $row=array_map(static function ($value): string {
                $value=(string)$value;
                return preg_match('/^(?:[\s\p{Z}]*[=+@-]|[\t\r\n])/u',$value) ? "'".$value : $value;
            },$row);
            fputcsv($output,$row,',','"','');
        }
        fclose($output);
        exit;
    }
    $pageCount=max(1,(int)ceil(count($filtered)/25));
    $page=min($pageCount,max(1,(int)($_GET['page']??1)));
    $visibleRequests=array_slice($filtered,($page-1)*25,25);
}
?><!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Xchange Portal</title><link rel="stylesheet" href="assets/app.css"><link rel="stylesheet" href="assets/theme.css"><script src="assets/app.js" defer></script></head><body>
<?php if (!$me): ?>
<main class="login"><form class="panel" method="post"><div class="brand"><b>✦</b> Xchange Portal</div><h1>Bienvenido</h1><p class="muted">Colabora con tus clientes y gestiona los documentos de cada proyecto.</p>
<?php if($error):?><p class="message"><?=h($error)?></p><?php endif;?>
<input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="login">
<div class="field"><label for="email">Correo electrónico</label><input id="email" name="email" type="email" required autocomplete="username"></div>
<div class="field"><label for="password">Contraseña</label><input id="password" name="password" type="password" required autocomplete="current-password"></div>
<button type="submit">Iniciar sesión</button><p class="small muted" style="margin-top:20px">Entorno local · PHP / SQL · sin dependencias externas</p></form></main>
<?php else: ?>
<div class="app"><aside class="side"><div class="brand"><b>✦</b> Xchange</div><nav><a href="?project=<?=$projectId?>#projects">Proyectos</a><a href="?project=<?=$projectId?>" class="active">◫ &nbsp; Solicitudes</a><a href="?project=<?=$projectId?>#new-request">＋ &nbsp; Nueva solicitud</a><a href="?project=<?=$projectId?>#updates">Avisos</a><a href="?project=<?=$projectId?>#team">♙ &nbsp; Equipo</a></nav><p class="small muted" style="margin-top:35px">Portal seguro de intercambio de archivos</p></aside>
<main class="main"><header class="top"><div><p class="eyebrow">PROYECTOS / DOCUMENTOS</p><h1><?=h($selected?$selected['title']:'Centro de solicitudes')?></h1><p class="muted"><?=h($selected?$selected['project_name']:($activeProject['name']??'Sin proyectos asignados'))?></p></div><div class="topuser"><?=h($me['name'])?> <form method="post" style="display:inline-block;margin-left:12px"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="logout"><button class="secondary">Salir</button></form></div></header>
<?php if($error):?><p class="message"><?=h($error)?></p><?php endif;?>
<?php if($selected):?>
<p><a href="index.php?project=<?=$projectId?>">← Volver a solicitudes</a></p>
<div class="detail"><section class="stack"><div class="panel"><h2>Detalles de solicitud #<?=$selected['id']?></h2><p><?=nl2br(h($selected['description']))?></p><p><span class="pill <?=h($selected['status'])?>"><?=h($selected['status'])?></span> · Prioridad: <?=h($selected['priority'])?> · Fecha límite: <?=h($selected['due_date']??'Sin fecha')?></p><p class="muted">Asignado a: <?=h($selected['assigned_name']??'Sin asignar')?></p>
<?php if($selected['status']==='submitted' && in_array($role,['auditor','admin'],true)):?>
<form method="post" class="actions" data-confirm="¿Registrar revisión de esta solicitud?"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="status"><input type="hidden" name="request_id" value="<?=$selected['id']?>"><div class="field"><label>Comentario de revisión (obligatorio al devolver)<textarea name="reason" maxlength="5000"></textarea></label></div><button name="status" value="accepted">Aceptar</button><button class="secondary" name="status" value="returned">Devolver</button></form><?php endif;?></div>
<?php if (in_array($role,['admin','auditor'],true)): ?><details class="panel"><summary>Editar solicitud</summary><?php require dirname(__DIR__).'/app/request-form.php'; ?></details><?php endif; ?><div class="panel"><h2>Documentos compartidos</h2><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="upload"><input type="hidden" name="request_id" value="<?=$selected['id']?>"><div class="field"><label for="document">Subir PDF, Office, imágenes, ZIP, TXT o CSV (máx. 20MB)</label><input id="document" name="document" type="file" required></div><button>Subir documento</button></form>
<?php foreach($documents as $d):?><div class="file"><div><strong><?=h($d['original_name'])?></strong><div class="small muted">Versión <?=$d['version']?> · <?=h($d['author_name'])?> · <?=h($d['created_at'])?> · <?=number_format((int)$d['byte_size']/1024,1)?> KB</div></div><a href="?action=download&amp;file=<?=$d['id']?>">Descargar</a></div><?php endforeach;?>
<?php if(!$documents):?><p class="muted" style="margin-top:18px">No hay documentos compartidos todavía.</p><?php endif;?></div></section>
<section class="stack"><div class="panel"><h2>Comentarios</h2><?php foreach($comments as $c):?><div class="file"><div><strong><?=h($c['author_name'])?></strong> <span class="small muted"><?=h($c['created_at'])?></span><p><?=nl2br(h($c['body']))?></p></div></div><?php endforeach;?><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="comment"><input type="hidden" name="request_id" value="<?=$selected['id']?>"><div class="field"><label for="body">Nuevo comentario</label><textarea id="body" name="body" maxlength="5000" required></textarea></div><button>Publicar comentario</button></form></div><div class="panel"><h2>Actividad reciente</h2><?php foreach($events as $e):?><p class="small"><b><?=h($e['author_name'])?></b> · <?=h($e['action'])?><br><span class="muted"><?=h($e['created_at'])?> <?=h($e['details'])?></span></p><?php endforeach;?></div></section></div>
<?php else:?>
<section class="panel" id="projects">
  <h2>Proyectos</h2>
  <?php if (!$projects): ?><p class="muted">No tienes proyectos asignados.</p><?php endif; ?>
  <?php foreach ($projects as $availableProject): ?>
    <p><a href="?project=<?=$availableProject['id']?>" <?php if ((int)$availableProject['id']===$projectId): ?>aria-current="page"<?php endif; ?>><?=h($availableProject['name'])?></a></p>
  <?php endforeach; ?>
  <?php if ($activeProject && $activeProject['description']): ?><p><?=nl2br(h($activeProject['description']))?></p><?php endif; ?>
  <?php foreach (['create_project'=>'Nuevo proyecto','edit_project'=>'Editar proyecto'] as $projectAction=>$label): ?>
    <?php if ($projectAction==='create_project' ? !$canCreateProject : $role!=='admin') continue; ?>
    <?php $editing=$projectAction==='edit_project'; ?>
    <details>
      <summary><?=h($label)?></summary>
      <form method="post">
        <input type="hidden" name="csrf" value="<?=h(csrf())?>">
        <input type="hidden" name="action" value="<?=h($projectAction)?>">
        <?php if ($editing): ?><input type="hidden" name="project_id" value="<?=$projectId?>"><?php endif; ?>
        <div class="field"><label>Nombre <input name="name" maxlength="200" value="<?=h($editing?$activeProject['name']:'')?>" required></label></div>
        <div class="field"><label>Descripción <textarea name="description"><?=h($editing?$activeProject['description']:'')?></textarea></label></div>
        <button>Guardar proyecto</button>
      </form>
    </details>
  <?php endforeach; ?>
  <?php if ($role==='admin'): ?>
    <details>
      <summary>Historial de administración</summary>
      <?php $projectEventLabels=['project_created'=>'Proyecto creado','project_updated'=>'Proyecto editado','project_member_added'=>'Miembro añadido','project_member_role_changed'=>'Rol actualizado','project_member_removed'=>'Miembro retirado']; ?>
      <?php if (!$projectHistory): ?><p class="muted">Todavía no hay cambios registrados.</p><?php endif; ?>
      <?php foreach ($projectHistory as $event): ?>
        <p><?=h($projectEventLabels[$event['action']]??$event['action'])?> · <?=h($event['name'])?> · <?=h($event['created_at'])?><br><span class="muted"><?=h($event['details'])?></span></p>
      <?php endforeach; ?>
    </details>
  <?php endif; ?>
</section>
<div class="grid"><div class="panel"><div class="metric"><?=$counts['total']?></div><div class="metric-caption">Solicitudes totales</div></div><div class="panel"><div class="metric"><?=$counts['open']?></div><div class="metric-caption">Pendientes</div></div><div class="panel"><div class="metric"><?=$counts['submitted']?></div><div class="metric-caption">Recibidas</div></div><div class="panel"><div class="metric"><?=$counts['overdue']?></div><div class="metric-caption danger">Vencidas</div></div><div class="panel"><div class="metric"><?=$counts['accepted']?></div><div class="metric-caption">Aceptadas</div></div></div>
<section class="panel"><div class="toolbar"><div><h2>Solicitudes de documentos</h2><p class="muted">Intercambio, seguimiento y revisión de evidencia.</p></div><form method="get" class="request-filters">
  <input type="hidden" name="project" value="<?=$projectId?>">
  <input name="q" type="search" value="<?=h($filters['q'])?>" placeholder="Buscar solicitudes…" aria-label="Buscar solicitudes">
  <select name="status" aria-label="Estado"><option value="">Todos los estados</option><?php foreach ($statusLabels as $value=>$label): ?><option value="<?=$value?>" <?php if ($value===$filters['status']): ?>selected<?php endif; ?>><?=h($label)?></option><?php endforeach; ?></select>
  <select name="priority" aria-label="Prioridad"><option value="">Todas las prioridades</option><?php foreach ($priorityLabels as $value=>$label): ?><option value="<?=$value?>" <?php if ($value===$filters['priority']): ?>selected<?php endif; ?>><?=h($label)?></option><?php endforeach; ?></select>
  <select name="due" aria-label="Vencimiento"><option value="">Todos los vencimientos</option><?php foreach ($dueLabels as $value=>$label): ?><option value="<?=$value?>" <?php if ($value===$filters['due']): ?>selected<?php endif; ?>><?=h($label)?></option><?php endforeach; ?></select>
  <button>Filtrar</button>
  <?php if ($projectId): ?><a href="?<?=h(http_build_query($listQuery+['action'=>'export_requests']))?>">Descargar CSV</a><?php endif; ?>
</form></div>
<div class="table-wrap"><table><thead><tr><th>#</th><th>Solicitud</th><th>Asignado</th><th>Estado</th><th>Prioridad</th><th>Fecha límite</th></tr></thead><tbody><?php foreach($visibleRequests as $r):?><tr data-request-row><td><?=h((string)$r['id'])?></td><td><a href="?request=<?=$r['id']?>"><?=h($r['title'])?></a></td><td><?=h($r['assigned_name']??'—')?></td><td><span class="pill <?=h($r['status'])?>"><?=h($statusLabels[$r['status']])?></span></td><td><?=h($priorityLabels[$r['priority']])?></td><td><?=h($r['due_date']??'—')?></td></tr><?php endforeach;?></tbody></table><?php if (!$visibleRequests): ?><p class="muted">No hay solicitudes para mostrar.</p><?php endif; ?></div>
<?php if ($pageCount>1): ?>
  <nav class="actions" aria-label="Paginación de solicitudes">
    <?php if ($page>1): ?><a href="?<?=h(http_build_query($listQuery+['page'=>$page-1]))?>">Anterior</a><?php endif; ?>
    <span>Página <?=$page?> de <?=$pageCount?></span>
    <?php if ($page<$pageCount): ?><a href="?<?=h(http_build_query($listQuery+['page'=>$page+1]))?>">Siguiente</a><?php endif; ?>
  </nav>
<?php endif; ?></section>
<?php if(in_array($role,['auditor','admin'],true)):?><section class="panel" id="new-request" style="margin-top:20px"><h2>Nueva solicitud</h2><?php require dirname(__DIR__).'/app/request-form.php'; ?></section><?php endif;?>
<section class="panel" id="updates">
  <h2>Avisos del proyecto</h2>
  <h3>Vencidas o por vencer en los próximos 7 días</h3>
  <?php if (!$deadlines): ?><p class="muted">No hay solicitudes con vencimiento en ese período.</p><?php endif; ?>
  <?php foreach (array_slice($deadlines,0,10) as $deadline): ?>
    <p><a href="?request=<?=$deadline['id']?>"><?=h($deadline['title'])?></a> · <?=h($deadline['due_date'])?><?php if ($deadline['due_date']<$today): ?> · Vencida<?php endif; ?></p>
  <?php endforeach; ?>
  <h3>Actividad de otros miembros</h3>
  <?php $updateLabels=['request_created'=>'Nueva solicitud','request_updated'=>'Solicitud editada','document_uploaded'=>'Documento recibido','status_changed'=>'Estado actualizado','comment_added'=>'Nuevo comentario']; ?>
  <?php if (!$updates): ?><p class="muted">Todavía no hay avisos de otros miembros.</p><?php endif; ?>
  <?php foreach ($updates as $update): ?>
    <p><?=h($updateLabels[$update['action']])?> · <a href="?request=<?=$update['id']?>"><?=h($update['title'])?></a> <small class="muted"><?=h($update['created_at'])?></small></p>
  <?php endforeach; ?>
</section>
<section class="panel" id="team">
  <h2>Miembros del proyecto</h2>
  <?php $roleLabels=['admin'=>'Administrador','auditor'=>'Equipo de la compañía','client'=>'Cliente']; ?>
  <?php foreach ($members as $member): ?>
    <p><?=h($member['name'])?> · <?=h($roleLabels[$member['role']])?></p>
    <?php if ($role==='admin' && $member['role']!=='admin'): ?>
      <form method="post" class="actions">
        <input type="hidden" name="csrf" value="<?=h(csrf())?>">
        <input type="hidden" name="action" value="edit_member_role">
        <input type="hidden" name="project_id" value="<?=$projectId?>">
        <input type="hidden" name="member_id" value="<?=$member['id']?>">
        <select name="member_role" aria-label="Rol de <?=h($member['name'])?>">
          <?php foreach (['client','auditor'] as $value): ?><option value="<?=$value?>" <?php if ($member['role']===$value): ?>selected<?php endif; ?>><?=h($roleLabels[$value])?></option><?php endforeach; ?>
        </select>
        <button>Actualizar rol</button>
      </form>
      <form method="post" data-confirm="¿Retirar a este miembro y quitarle sus asignaciones en el proyecto?">
        <input type="hidden" name="csrf" value="<?=h(csrf())?>">
        <input type="hidden" name="action" value="remove_member">
        <input type="hidden" name="project_id" value="<?=$projectId?>">
        <input type="hidden" name="member_id" value="<?=$member['id']?>">
        <button class="secondary">Retirar del proyecto</button>
      </form>
    <?php endif; ?>
  <?php endforeach; ?>
  <?php if ($role==='admin'): ?>
    <details>
      <summary>Añadir miembro</summary>
      <p class="muted">Si la cuenta existe, indica su correo y el rol en este proyecto. Para crearla, completa también el nombre y la contraseña.</p>
      <form method="post">
        <input type="hidden" name="csrf" value="<?=h(csrf())?>">
        <input type="hidden" name="action" value="add_member">
        <input type="hidden" name="project_id" value="<?=$projectId?>">
        <div class="field"><label>Correo <input name="email" type="email" maxlength="190" required></label></div>
        <div class="field"><label>Rol <select name="member_role"><option value="client">Cliente</option><option value="auditor">Equipo de la compañía</option></select></label></div>
        <div class="field"><label>Nombre de la cuenta nueva <input name="name" maxlength="150"></label></div>
        <div class="field"><label>Contraseña de la cuenta nueva <input type="password" name="password" autocomplete="new-password" minlength="12" maxlength="72"></label></div>
        <button>Añadir miembro</button>
      </form>
    </details>
  <?php endif; ?>
</section>
<?php endif;?></main></div><?php endif;?></body></html>
