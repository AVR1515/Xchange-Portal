<?php
declare(strict_types=1);
ini_set('display_errors','0');
set_exception_handler(static function (Throwable $exception): void {
    $incident=bin2hex(random_bytes(8));
    error_log(sprintf('Xchange Portal [%s] %s in %s:%d', $incident, get_class($exception), $exception->getFile(), $exception->getLine()));
    http_response_code(500);
    header('Cache-Control: no-store');
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        .'<title>Portal no disponible · Xchange</title><link rel="stylesheet" href="assets/app.css"><link rel="stylesheet" href="assets/theme.css">'
        .'<main class="login"><section class="panel"><h1>El portal no está disponible</h1><p>Vuelve a intentarlo en unos momentos. Si el problema continúa, comparte este código con el administrador.</p>'
        .'<p class="muted">Código: '.$incident.'</p><a class="btn" href="index.php">Volver al portal</a></section></main></html>';
});
require dirname(__DIR__).'/app/bootstrap.php';
$action=(string)($_GET['action']??'');
$error='';
$requestFilters=array_intersect_key($_GET,array_flip(['q','status','priority','due','mine','sort','page']));
$requestFilters=array_filter($requestFilters,'is_string');
$requestUrl=static fn(int $id,array $context): string => '?'.http_build_query(['request'=>$id]+$context);
$flash=$_SESSION['portal_flash']??'';
unset($_SESSION['portal_flash']);
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
            $_SESSION['auth_version']=hash('sha256',$user['password_hash']);
            go();
        }
        if ($postAction==='logout') {
            $_SESSION=[]; session_regenerate_id(true); go();
        }
        $actor=requireUser(); $uid=(int)$actor['id'];
        if ($postAction==='change_password') {
            $current=(string)($_POST['current_password']??'');
            $new=(string)($_POST['new_password']??'');
            $confirmation=(string)($_POST['confirm_password']??'');
            if (strlen($new)<12 || strlen($new)>72 || str_contains($new,chr(0)) || $new!==$confirmation) {
                throw new RuntimeException('La nueva contraseña debe tener entre 12 y 72 bytes y coincidir con la confirmación');
            }
            $query=db()->prepare('SELECT password_hash FROM users WHERE id=? AND is_active=1');
            $query->execute([$uid]);
            $previous=$query->fetchColumn();
            if (!$previous || !password_verify($current,$previous)) throw new RuntimeException('La contraseña actual es incorrecta');
            if (password_verify($new,$previous)) throw new RuntimeException('Usa una contraseña diferente de la actual');
            $query=db()->prepare('UPDATE users SET password_hash=? WHERE id=? AND password_hash=? AND is_active=1');
            $query->execute([password_hash($new,PASSWORD_DEFAULT),$uid,$previous]);
            if ($query->rowCount()!==1) throw new RuntimeException('La cuenta cambió. Vuelve a iniciar sesión');
            $_SESSION=[];
            session_regenerate_id(true);
            go('?password_changed=1');
        }
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
            go('?project='.$project.'&view=team');
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
            go('?project='.$project.'&view=team');
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
            go('?project='.$project.'&view=projects');
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
            $_SESSION['portal_flash']=$editing?'Cambios guardados.':'Solicitud creada.';
            go($requestUrl($rid,$requestFilters));
        }
        $rid=(int)($_POST['request_id']??0);
        $req=getRequest($rid,$uid);
        $role=membership((int)$req['project_id'],$uid)['role'];
        if ($postAction==='comment') {
            $body=trim((string)($_POST['body']??''));
            if ($body==='' || mb_strlen($body)>5000) throw new RuntimeException('Comentario inválido');
            db()->prepare('INSERT INTO request_comments(request_id,user_id,body) VALUES(?,?,?)')->execute([$rid,$uid,$body]);
            recordEvent((int)$req['project_id'],$rid,$uid,'comment_added');
            $_SESSION['portal_flash']='Comentario publicado.';
            go($requestUrl($rid,$requestFilters).'#comments');
        }
        if ($postAction==='status') {
            if (!in_array($role,['auditor','admin'],true)) throw new RuntimeException('Sin permisos de revisión');
            $status=(string)($_POST['status']??'');
            $allowed=['submitted'=>['accepted','returned','cancelled'],'returned'=>['cancelled'],'open'=>['cancelled']];
            if (!in_array($status,$allowed[$req['status']]??[],true)) throw new RuntimeException('Transición de estado inválida');
            $reason=trim((string)($_POST['reason']??''));
            if ((in_array($status,['returned','cancelled'],true) && $reason==='') || mb_strlen($reason)>5000) {
                throw new RuntimeException('Indica un motivo de hasta 5000 caracteres para devolver o cancelar la solicitud');
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
            $_SESSION['portal_flash']='Revisión registrada.';
            go($requestUrl($rid,$requestFilters));
        }
        if ($postAction==='upload') {
            if (in_array($req['status'],['accepted','cancelled'],true)) {
                throw new RuntimeException('La solicitud está cerrada y no admite nuevos documentos');
            }
            $files=$_FILES['document']??[];
            $names=(array)($files['name']??[]);
            if (!$names || count($names)>10) throw new RuntimeException('Selecciona entre 1 y 10 archivos');
            $uploads=[];
            $types=new finfo(FILEINFO_MIME_TYPE);
            $allowed=[
                'pdf'=>['application/pdf'],'png'=>['image/png'],'jpg'=>['image/jpeg'],'jpeg'=>['image/jpeg'],
                'txt'=>['text/plain'],'csv'=>['text/plain','text/csv','application/csv','application/vnd.ms-excel'],
                'docx'=>['application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/zip'],
                'xlsx'=>['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/zip'],
                'zip'=>['application/zip','application/x-zip-compressed']
            ];
            foreach ($names as $i=>$original) {
                $tmp=((array)($files['tmp_name']??[]))[$i]??'';
                $size=(int)(((array)($files['size']??[]))[$i]??0);
                $errorCode=((array)($files['error']??[]))[$i]??UPLOAD_ERR_NO_FILE;
                if ($errorCode!==UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
                    throw new RuntimeException('Falló la carga de un archivo o se excedió el límite de PHP');
                }
                if ($size<=0 || $size>(int)$config['max_upload_bytes']) {
                    throw new RuntimeException('Cada archivo debe tener contenido y respetar el tamaño máximo');
                }
                $original=basename(str_replace('\\','/',(string)$original));
                $original=preg_replace('/[\x00-\x1f\x7f]/u','',$original)?:'documento';
                if (strlen($original)>255) throw new RuntimeException('Nombre de archivo demasiado largo');
                $ext=strtolower(pathinfo($original,PATHINFO_EXTENSION));
                $mime=$types->file($tmp);
                if (!isset($allowed[$ext]) || !in_array($mime,$allowed[$ext],true)) {
                    throw new RuntimeException('Tipo de archivo no permitido: '.$original);
                }
                $uploads[]=[$tmp,$size,$original,$mime];
            }
            $dir=$config['storage_path'];
            if (!is_dir($dir) && !mkdir($dir,0700,true) && !is_dir($dir)) throw new RuntimeException('Almacenamiento no disponible');
            $targets=[];
            try {
                db()->beginTransaction();
                $lock=db()->prepare('SELECT status FROM requests WHERE id=? FOR UPDATE');
                $lock->execute([$rid]);
                if (!in_array($lock->fetchColumn(),['open','returned','submitted'],true)) {
                    throw new RuntimeException('La solicitud está cerrada y no admite nuevos documentos');
                }
                $insert=db()->prepare('INSERT INTO documents(request_id,uploaded_by,original_name,storage_name,mime_type,byte_size,sha256) VALUES(?,?,?,?,?,?,?)');
                foreach ($uploads as [$tmp,$size,$original,$mime]) {
                    $name=bin2hex(random_bytes(24)).'.bin';
                    $target=$dir.'/'.$name;
                    if (!move_uploaded_file($tmp,$target)) throw new RuntimeException('No se pudo almacenar el archivo');
                    $targets[]=$target;
                    $hash=hash_file('sha256',$target);
                    if ($hash===false) throw new RuntimeException('No se pudo verificar el archivo');
                    $insert->execute([$rid,$uid,$original,$name,$mime,$size,$hash]);
                    recordEvent((int)$req['project_id'],$rid,$uid,'document_uploaded',$original);
                }
                db()->prepare("UPDATE requests SET status='submitted' WHERE id=? AND status IN ('open','returned')")->execute([$rid]);
                db()->commit();
            } catch (Throwable $ex) { if (db()->inTransaction()) db()->rollBack(); foreach ($targets as $target) @unlink($target); throw $ex; }
            $_SESSION['portal_flash']=count($uploads)===1?'Archivo enviado.':count($uploads).' archivos enviados.';
            go($requestUrl($rid,$requestFilters).'#documents');
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
    $statusLabels=['open'=>'Pendiente','submitted'=>'Recibida','returned'=>'Devuelta','accepted'=>'Aceptada','cancelled'=>'Cancelada'];
    $priorityLabels=['normal'=>'Normal','high'=>'Alta','critical'=>'Crítica'];
    $counts=['total'=>count($requests),'open'=>0,'submitted'=>0,'returned'=>0,'accepted'=>0,'cancelled'=>0,'overdue'=>0];
    $today=date('Y-m-d');
    $nextWeek=date('Y-m-d',strtotime('+7 days'));
    $deadlines=[];
    foreach($requests as $r) {
        if (isset($counts[$r['status']])) $counts[$r['status']]++;
        if ($r['due_date'] && !in_array($r['status'],['accepted','cancelled'],true)) {
            if ($r['due_date']<$today) $counts['overdue']++;
            if ($r['due_date']<=$nextWeek) $deadlines[]=$r;
        }
    }
    usort($deadlines,static fn(array $left,array $right): int => $left['due_date']<=>$right['due_date']);
    $filters=[
        'q'=>trim((string)($requestFilters['q']??'')),
        'status'=>(string)($requestFilters['status']??''),
        'priority'=>(string)($requestFilters['priority']??''),
        'due'=>(string)($requestFilters['due']??''),
        'mine'=>($requestFilters['mine']??'')==='1'?'1':'',
        'sort'=>($requestFilters['sort']??'')==='due'?'due':'',
    ];
    $dueLabels=['overdue'=>'Vencidas','upcoming'=>'Próximos 7 días','none'=>'Sin fecha'];
    $filtered=array_values(array_filter($requests,static function (array $request) use ($filters,$today,$nextWeek,$uid): bool {
        $due=$request['due_date'];
        $isOpen=!in_array($request['status'],['accepted','cancelled'],true);
        $matchesDue=match($filters['due']) {
            'overdue'=>$isOpen && $due!==null && $due<$today,
            'upcoming'=>$isOpen && $due!==null && $due>=$today && $due<=$nextWeek,
            'none'=>$due===null,
            default=>true,
        };
        return ($filters['status']==='' || $filters['status']===$request['status'])
            && ($filters['priority']==='' || $filters['priority']===$request['priority'])
            && $matchesDue
            && ($filters['mine']==='' || (int)$request['assigned_to']===$uid)
            && ($filters['q']==='' || mb_stripos($request['title'].' '.$request['assigned_name'], $filters['q'])!==false);
    }));
    if ($filters['sort']==='due') {
        usort($filtered,static fn(array $a,array $b): int =>
            ($a['due_date']??'9999-12-31')<=>($b['due_date']??'9999-12-31'));
    }
    $views=['requests'=>'Solicitudes','projects'=>'Proyectos','updates'=>'Actividad','team'=>'Equipo','account'=>'Mi cuenta'];
    if (in_array($role,['admin','auditor'],true)) $views['new']='Nueva solicitud';
    $view=(string)($_GET['view']??(isset($_GET['project'])?'requests':'projects'));
    if ($selected || !isset($views[$view])) $view='requests';
    $projectStats=[];
    if ($view==='projects' && !$selected) {
        $summary=db()->prepare("SELECT pm.project_id, COUNT(r.id) AS total,
            COUNT(CASE WHEN r.status='accepted' THEN 1 END) AS accepted,
            COUNT(CASE WHEN r.status IN ('open','submitted','returned') THEN 1 END) AS active,
            COUNT(CASE WHEN r.status IN ('open','submitted','returned') AND r.due_date<? THEN 1 END) AS overdue
            FROM project_members pm LEFT JOIN requests r ON r.project_id=pm.project_id
            WHERE pm.user_id=? GROUP BY pm.project_id");
        $summary->execute([$today,$uid]);
        foreach ($summary as $row) $projectStats[$row['project_id']]=$row;
    }
    $viewUrl=static fn(string $key): string => '?'.http_build_query(['project'=>$projectId,'view'=>$key]);
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
    $page=min($pageCount,max(1,(int)($requestFilters['page']??1)));
    $visibleRequests=array_slice($filtered,($page-1)*25,25);
    $requestActivity=[];
    if ($view==='requests' && $visibleRequests) {
        $ids=array_column($visibleRequests,'id');
        $placeholders=implode(',',array_fill(0,count($ids),'?'));
        $activity=db()->prepare("SELECT r.id,
            (SELECT COUNT(*) FROM documents d WHERE d.request_id=r.id) AS documents,
            (SELECT COUNT(*) FROM request_comments c WHERE c.request_id=r.id) AS comments
            FROM requests r WHERE r.id IN ($placeholders)");
        $activity->execute($ids);
        foreach ($activity as $row) $requestActivity[$row['id']]=$row;
    }
    $chartStart=new DateTimeImmutable($today);
    $dueBuckets=array_fill(0,5,0);
    $chartRequests=$view==='projects'
        ? [] : $requests;
    if ($view==='projects') {
        $dueQuery=db()->prepare("SELECT r.due_date FROM requests r JOIN project_members pm ON pm.project_id=r.project_id
            WHERE pm.user_id=? AND r.status IN ('open','submitted','returned') AND r.due_date BETWEEN ? AND ?");
        $dueQuery->execute([$uid,$today,$chartStart->modify('+29 days')->format('Y-m-d')]);
        $chartRequests=$dueQuery->fetchAll();
    }
    foreach ($chartRequests as $item) {
        if (!$item['due_date'] || in_array($item['status']??'', ['accepted','cancelled'],true)) continue;
        $days=(int)$chartStart->diff(new DateTimeImmutable($item['due_date']))->format('%r%a');
        if ($days>=0 && $days<30) $dueBuckets[intdiv($days,7)]++;
    }
}
?><!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Xchange Portal</title><link rel="stylesheet" href="assets/app.css"><link rel="stylesheet" href="assets/theme.css"><script src="assets/app.js" defer></script></head><body>
<?php if (!$me): ?>
<main class="login"><form class="panel" method="post"><div class="brand"><b>✦</b> Xchange Portal</div><h1>Bienvenido</h1><p class="muted">Colabora con tus clientes y gestiona los documentos de cada proyecto.</p>
<?php if($error):?><p class="message"><?=h($error)?></p><?php endif;?>
<?php if (isset($_GET['password_changed'])): ?><p class="message">Contraseña actualizada. Inicia sesión con la nueva contraseña.</p><?php endif; ?>
<input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="login">
<div class="field"><label for="email">Correo electrónico</label><input id="email" name="email" type="email" required autocomplete="username"></div>
<div class="field"><label for="password">Contraseña</label><input id="password" name="password" type="password" required autocomplete="current-password"></div>
<button type="submit">Iniciar sesión</button><p class="small muted" style="margin-top:20px">Entorno local · PHP / SQL · sin dependencias externas</p></form></main>
<?php else: ?>
<a class="skip-link" href="#main-content">Saltar al contenido</a>
<div class="app">
<header class="portal-bar">
  <a class="portal-brand" href="?view=projects"><b class="brand-icon">X</b> Xchange Portal</a>
  <?php if ($activeProject && $view!=='projects'): ?><span class="portal-project"><?=h($activeProject['name'])?></span><?php endif; ?>
  <div class="topuser">
    <a class="account-link" href="<?=h($viewUrl('account'))?>"><?=h($me['name'])?></a>
    <form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="logout"><button class="secondary">Salir</button></form>
  </div>
  <nav class="workspace-nav" aria-label="Navegación del proyecto">
    <?php foreach ($views as $key=>$label): ?>
      <?php if ($key==='new') continue; ?>
      <a href="<?=h($viewUrl($key))?>" <?php if ($view===$key): ?>aria-current="page"<?php endif; ?>><?=h($label)?></a>
    <?php endforeach; ?>
  </nav>
</header>
<main class="main" id="main-content" tabindex="-1">
  <?php if ($selected || $view!=='projects'): ?><div class="page-heading">
    <div>
      <p class="eyebrow"><?=h($activeProject['name']??'Espacio de trabajo')?></p>
      <h1><?=h($views[$view])?></h1>
    </div>
    <?php if (!$selected && $view==='requests' && isset($views['new'])): ?>
      <a class="btn" href="<?=h($viewUrl('new'))?>">＋ Nueva solicitud</a>
    <?php endif; ?>
  </div><?php endif; ?>
<?php if($error && !$selected):?><p class="message"><?=h($error)?></p><?php endif;?>
<?php if ($flash && !$selected): ?><p class="message success" role="status"><?=h($flash)?></p><?php endif; ?>
<?php if ($view==='projects'): ?>
<section id="projects">
  <div class="page-heading workspace-heading">
    <div><h1>Mis proyectos</h1><p class="muted">Consulta el avance y abre las solicitudes de cada proyecto.</p></div>
    <?php if ($canCreateProject): ?><a class="btn" href="<?=h($viewUrl('projects'))?>&amp;create=1#project-editor">＋ Nuevo proyecto</a><?php endif; ?>
  </div>
  <div class="overview-layout">
  <div class="project-directory">
  <h2>Espacios de trabajo <span class="result-count"><?=count($projects)?></span></h2>
  <label class="small muted" for="project-search">Buscar proyectos</label>
  <input type="search" id="project-search" placeholder="Nombre del proyecto">
  <?php if (!$projects): ?><p class="muted">No tienes proyectos asignados.</p><?php endif; ?>
  <div class="project-grid">
    <?php foreach ($projects as $availableProject): ?>
      <?php $stats=$projectStats[$availableProject['id']]; $percent=$stats['total']?round(100*$stats['accepted']/$stats['total']):0; ?>
      <article class="panel project-card" data-project-card>
        <div class="project-card-top"><span class="brand-icon" aria-hidden="true"><?=h(mb_strtoupper(mb_substr($availableProject['name'],0,1)))?></span><span class="result-count"><?=$percent?>%</span></div>
        <h3><?=h($availableProject['name'])?></h3>
        <?php if ($availableProject['description']): ?><p class="muted"><?=h($availableProject['description'])?></p><?php endif; ?>
        <div class="project-card-status"><span class="small muted"><?=$stats['total']?> solicitudes · <?=$stats['accepted']?> aceptadas</span>
        <?php if ($stats['overdue']): ?><a class="project-overdue" href="?project=<?=$availableProject['id']?>&amp;due=overdue"><?=$stats['overdue']?> vencidas</a><?php endif; ?>
        <progress max="100" value="<?=$percent?>" aria-label="Porcentaje de solicitudes aceptadas"></progress></div>
        <div class="actions"><a class="btn" href="?project=<?=$availableProject['id']?>">Abrir</a>
        <?php if ($availableProject['role']==='admin'): ?><a class="btn secondary" href="?project=<?=$availableProject['id']?>&amp;view=projects&amp;edit=1#project-editor">Editar</a><?php endif; ?></div>
      </article>
    <?php endforeach; ?>
  </div>
  <p id="project-search-empty" class="muted" hidden>No hay proyectos que coincidan.</p>
  </div>
  <div class="overview-data">
    <div class="overview-top">
      <section class="panel overview-summary">
        <h2>Resumen</h2>
        <?php $allTotal=array_sum(array_column($projectStats,'total')); $allAccepted=array_sum(array_column($projectStats,'accepted')); ?>
        <div class="overview-total"><strong><?=$allTotal?></strong><span>Solicitudes</span></div>
        <dl><div><dt>Activas</dt><dd><?=array_sum(array_column($projectStats,'active'))?></dd></div><div><dt>Aceptadas</dt><dd><?=$allAccepted?></dd></div></dl>
        <progress max="<?=max(1,$allTotal)?>" value="<?=$allAccepted?>" aria-label="Solicitudes aceptadas"></progress>
      </section>
      <?php require dirname(__DIR__).'/app/due-chart.php'; ?>
    </div>
    <section class="panel attention-panel"><h2>Requieren atención</h2>
      <?php foreach ($projects as $project): ?>
        <?php if (!$projectStats[$project['id']]['overdue']) continue; ?>
        <p><a href="?project=<?=$project['id']?>&amp;due=overdue"><?=h($project['name'])?></a> <span class="result-count"><?=$projectStats[$project['id']]['overdue']?></span></p>
      <?php endforeach; ?>
      <?php if (!array_sum(array_column($projectStats,'overdue'))): ?><p class="muted">No hay solicitudes vencidas en tus proyectos.</p><?php endif; ?>
    </section>
  </div>
  </div>
  <div id="project-editor" class="project-editor">
  <?php foreach (['create_project'=>'Nuevo proyecto','edit_project'=>'Editar proyecto'] as $projectAction=>$label): ?>
    <?php if ($projectAction==='create_project' ? !$canCreateProject : $role!=='admin') continue; ?>
    <?php $editing=$projectAction==='edit_project'; ?>
    <details class="panel" <?php if (isset($_GET[$editing?'edit':'create']) || ($error && $postAction===$projectAction)): ?>open<?php endif; ?>>
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
    <details class="panel">
      <summary>Historial de administración</summary>
      <?php $projectEventLabels=['project_created'=>'Proyecto creado','project_updated'=>'Proyecto editado','project_member_added'=>'Miembro añadido','project_member_role_changed'=>'Rol actualizado','project_member_removed'=>'Miembro retirado']; ?>
      <?php if (!$projectHistory): ?><p class="muted">Todavía no hay cambios registrados.</p><?php endif; ?>
      <?php foreach ($projectHistory as $event): ?>
        <p><?=h($projectEventLabels[$event['action']]??$event['action'])?> · <?=h($event['name'])?> · <?=h($event['created_at'])?><br><span class="muted"><?=h($event['details'])?></span></p>
      <?php endforeach; ?>
    </details>
  <?php endif; ?>
  </div>
</section>
<?php elseif ($view==='account'): ?>
<details class="panel" id="account" open>
  <summary>Cambiar contraseña</summary>
  <form method="post">
    <input type="hidden" name="csrf" value="<?=h(csrf())?>">
    <input type="hidden" name="action" value="change_password">
    <div class="field"><label>Contraseña actual <input name="current_password" type="password" autocomplete="current-password" required></label></div>
    <div class="field"><label>Nueva contraseña <input name="new_password" type="password" autocomplete="new-password" minlength="12" maxlength="72" required></label></div>
    <div class="field"><label>Confirmar nueva contraseña <input name="confirm_password" type="password" autocomplete="new-password" minlength="12" maxlength="72" required></label></div>
    <button>Actualizar contraseña</button>
  </form>
</details>
<?php elseif ($view==='requests'): ?>
<div class="request-overview"><div class="request-stats" aria-label="Resumen de solicitudes">
  <?php foreach (['total'=>'Todas','open'=>'Pendientes','submitted'=>'Recibidas','overdue'=>'Vencidas','accepted'=>'Aceptadas'] as $metric=>$label): ?>
    <?php $target=$metric==='overdue'?['due'=>'overdue']:($metric==='total'?[]:['status'=>$metric]); ?>
    <a href="?<?=h(http_build_query(['project'=>$projectId]+$target))?>" class="stat-card stat-<?=$metric?>">
      <span><?=h($label)?></span><strong><?=$counts[$metric]?></strong>
    </a>
  <?php endforeach; ?>
</div>
<?php require dirname(__DIR__).'/app/due-chart.php'; ?></div>
<section class="panel"><div class="toolbar"><div><h2>Lista de solicitudes <span class="result-count"><?=count($filtered)?></span></h2></div><form method="get" class="request-filters">
  <input type="hidden" name="project" value="<?=$projectId?>">
  <input name="q" type="search" value="<?=h($filters['q'])?>" placeholder="Buscar solicitudes…" aria-label="Buscar solicitudes">
  <select name="status" aria-label="Estado"><option value="">Todos los estados</option><?php foreach ($statusLabels as $value=>$label): ?><option value="<?=$value?>" <?php if ($value===$filters['status']): ?>selected<?php endif; ?>><?=h($label)?></option><?php endforeach; ?></select>
  <select name="priority" aria-label="Prioridad"><option value="">Todas las prioridades</option><?php foreach ($priorityLabels as $value=>$label): ?><option value="<?=$value?>" <?php if ($value===$filters['priority']): ?>selected<?php endif; ?>><?=h($label)?></option><?php endforeach; ?></select>
  <select name="due" aria-label="Vencimiento"><option value="">Todos los vencimientos</option><?php foreach ($dueLabels as $value=>$label): ?><option value="<?=$value?>" <?php if ($value===$filters['due']): ?>selected<?php endif; ?>><?=h($label)?></option><?php endforeach; ?></select>
    <select name="sort" aria-label="Orden"><option value="">Más recientes</option><option value="due" <?php if ($filters['sort']==='due'): ?>selected<?php endif; ?>>Fecha límite</option></select>
  <label class="mine-filter"><input type="checkbox" name="mine" value="1" <?php if ($filters['mine']==='1'): ?>checked<?php endif; ?>> Asignadas a mí</label>
  <button>Filtrar</button>
  <?php if (array_filter($filters,static fn(string $value): bool => $value!=='')): ?>
    <a href="?project=<?=$projectId?>">Limpiar filtros</a>
  <?php endif; ?>
  <?php if ($projectId): ?><a href="?<?=h(http_build_query($listQuery+['action'=>'export_requests']))?>">Descargar CSV</a><?php endif; ?>
</form></div>
<div class="table-wrap"><table><thead><tr><th>#</th><th>Solicitud</th><th>Asignado</th><th>Estado</th><th>Prioridad</th><th>Fecha límite</th><th>Actividad</th></tr></thead><tbody><?php foreach($visibleRequests as $r):?><tr id="request-<?=$r['id']?>" data-request-row <?php if ((int)($selected['id']??0)===(int)$r['id']): ?>class="request-current"<?php endif; ?>><td><?=h((string)$r['id'])?></td><td><a href="<?=h($requestUrl((int)$r['id'],$requestFilters))?>"><?=h($r['title'])?></a>
<button type="button" class="preview-trigger secondary" data-preview="preview-<?=$r['id']?>" aria-label="Vista rápida de <?=h($r['title'])?>" hidden>Vista rápida</button>
<template id="preview-<?=$r['id']?>">
  <h2><?=h($r['title'])?></h2>
  <p><?=nl2br(h($r['description']))?></p>
  <dl><dt>Estado</dt><dd><?=h($statusLabels[$r['status']])?></dd>
  <dt>Responsable</dt><dd><?=h($r['assigned_name']??'Sin asignar')?></dd>
  <dt>Fecha límite</dt><dd><?=h($r['due_date']??'Sin fecha')?></dd></dl>
  <a class="btn" href="<?=h($requestUrl((int)$r['id'],$requestFilters))?>">Abrir solicitud</a>
</template></td><td><?=h($r['assigned_name']??'—')?></td><td><span class="pill <?=h($r['status'])?>"><?=h($statusLabels[$r['status']])?></span></td><td><?=h($priorityLabels[$r['priority']])?></td><td><?=h($r['due_date']??'—')?></td><td><div class="row-activity">
  <?php foreach (['documents'=>['Documentos','#documents'],'comments'=>['Comentarios','#comments']] as $kind=>[$label,$anchor]): ?>
    <?php $count=(int)($requestActivity[$r['id']][$kind]??0); ?>
    <?php if ($count): ?><a href="<?=h($requestUrl((int)$r['id'],$requestFilters).$anchor)?>" aria-label="<?=h($label.' ('.$count.') de '.$r['title'])?>"><?=h($label)?> <?=$count?></a><?php endif; ?>
  <?php endforeach; ?>
  <?php if (empty($requestActivity[$r['id']]['documents']) && empty($requestActivity[$r['id']]['comments'])): ?><span class="muted">—</span><?php endif; ?>
</div></td></tr><?php endforeach;?></tbody></table><?php if (!$visibleRequests): ?><div class="empty-list">
  <strong><?=$requests?'No hay coincidencias':'Todavía no hay solicitudes'?></strong>
  <p class="muted"><?=$requests?'Cambia o limpia los filtros para ampliar la búsqueda.':'Las solicitudes de documentos de este proyecto aparecerán aquí.'?></p>
  <?php if (!$requests && isset($views['new'])): ?><a href="<?=h($viewUrl('new'))?>">Crear la primera solicitud</a><?php endif; ?>
</div><?php endif; ?></div>
<dialog class="request-preview" aria-label="Vista rápida de solicitud">
  <form method="dialog"><button class="secondary" autofocus>Cerrar</button></form>
  <div class="preview-content"></div>
</dialog>
<?php if ($pageCount>1): ?>
  <nav class="actions" aria-label="Paginación de solicitudes">
    <?php if ($page>1): ?><a href="?<?=h(http_build_query($listQuery+['page'=>$page-1]))?>">Anterior</a><?php endif; ?>
    <span>Página <?=$page?> de <?=$pageCount?></span>
    <?php if ($page<$pageCount): ?><a href="?<?=h(http_build_query($listQuery+['page'=>$page+1]))?>">Siguiente</a><?php endif; ?>
  </nav>
<?php endif; ?></section>
<?php elseif ($view==='new'): ?><section class="panel" id="new-request"><?php require dirname(__DIR__).'/app/request-form.php'; ?></section>
<?php elseif ($view==='updates'): ?>
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
<?php elseif ($view==='team'): ?>
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
<?php endif; ?>
<?php if ($selected): ?>
<dialog class="request-drawer" open aria-labelledby="drawer-title">
  <header class="drawer-header">
    <h2 id="drawer-title">#<?=$selected['id']?> <span><?=h($selected['title'])?></span></h2>
    <a class="btn secondary" href="?<?=h(http_build_query(['project'=>$projectId]+$requestFilters))?>#request-<?=$selected['id']?>" aria-label="Cerrar detalle" autofocus>×</a>
  </header>
  <nav class="drawer-tabs" aria-label="Secciones de la solicitud">
    <a href="#request-information">Detalle y documentos <span><?=count($documents)?></span></a>
    <a href="#comments">Comentarios <span><?=count($comments)?></span></a>
    <a href="#request-history">Historial <span><?=count($events)?></span></a>
  </nav>
  <div class="drawer-body">
    <?php if ($error): ?><p class="message" role="alert"><?=h($error)?></p><?php endif; ?>
    <?php if ($flash): ?><p class="message success" role="status"><?=h($flash)?></p><?php endif; ?>
    <?php require dirname(__DIR__).'/app/request-detail.php'; ?>
  </div>
  <footer class="drawer-footer">
    <?php $position=array_search((int)$selected['id'],array_map('intval',array_column($filtered,'id')),true); ?>
    <?php if ($position!==false && $position>0): ?><a class="btn secondary" href="<?=h($requestUrl((int)$filtered[$position-1]['id'],['page'=>intdiv($position-1,25)+1]+$requestFilters))?>">← Anterior</a><?php endif; ?>
    <?php if ($position!==false && isset($filtered[$position+1])): ?><a class="btn secondary" href="<?=h($requestUrl((int)$filtered[$position+1]['id'],['page'=>intdiv($position+1,25)+1]+$requestFilters))?>">Siguiente →</a><?php endif; ?>
  </footer>
</dialog>
<?php endif; ?>
</main></div><?php endif; ?></body></html>
