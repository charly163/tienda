<?php
declare(strict_types=1);
session_start();

$config = require __DIR__ . '/config.php';
$entities = [
    'productos' => ['label'=>'Productos','singular'=>'Producto','icon'=>'▣','columns'=>[
        'id'=>['ID','number','readonly'],'nombre'=>['Nombre','text','required'],'descripcion'=>['Descripción','textarea',''],
        'categoria_id'=>['Categoría','relation:categorias',''],'precio'=>['Precio','number','required','step'=>'0.01','min'=>'0'],
        'stock'=>['Stock','number','','min'=>'0'],'proveedor_id'=>['Proveedor','relation:proveedores','']],
        'search'=>['nombre','descripcion'], 'display'=>['nombre','precio','stock'], 'title'=>'nombre'],
    'categorias' => ['label'=>'Categorías','singular'=>'Categoría','icon'=>'◈','columns'=>[
        'id'=>['ID','number','readonly'],'nombre'=>['Nombre','text','required'],'descripcion'=>['Descripción','textarea','']],
        'search'=>['nombre','descripcion'],'display'=>['nombre','descripcion'],'title'=>'nombre'],
    'clientes' => ['label'=>'Clientes','singular'=>'Cliente','icon'=>'♙','columns'=>[
        'id'=>['ID','number','readonly'],'nombre'=>['Nombre','text','required'],'email'=>['Email','email',''],
        'telefono'=>['Teléfono','text',''],'direccion'=>['Dirección','text',''],'ciudad'=>['Ciudad','text',''],
        'fecha_registro'=>['Fecha de registro','date','']], 'search'=>['nombre','email','telefono'],
        'display'=>['nombre','email','telefono'],'title'=>'nombre'],
    'proveedores' => ['label'=>'Proveedores','singular'=>'Proveedor','icon'=>'⬡','columns'=>[
        'id'=>['ID','number','readonly'],'nombre'=>['Nombre','text','required'],'contacto'=>['Contacto','text',''],
        'telefono'=>['Teléfono','text',''],'email'=>['Email','email','']], 'search'=>['nombre','contacto','email'],
        'display'=>['nombre','contacto','telefono'],'title'=>'nombre'],
    'ventas' => ['label'=>'Ventas','singular'=>'Venta','icon'=>'↗','columns'=>[
        'id'=>['ID','number','readonly'],'cliente_id'=>['Cliente','relation:clientes','required'],
        'fecha'=>['Fecha','date',''],'total'=>['Total','number','required','step'=>'0.01','min'=>'0'],
        'metodo_pago'=>['Método de pago','text','']], 'search'=>[], 'display'=>['cliente_id','fecha','total'],'title'=>'id'],
    'detalle_ventas' => ['label'=>'Detalle de ventas','singular'=>'Detalle de venta','icon'=>'≡','columns'=>[
        'id'=>['ID','number','readonly'],'venta_id'=>['Venta','relation:ventas','required'],
        'producto_id'=>['Producto','relation:productos','required'],'cantidad'=>['Cantidad','number','required','min'=>'1'],
        'precio_unitario'=>['Precio unitario','number','required','step'=>'0.01','min'=>'0'],
        'subtotal'=>['Subtotal','number','required','step'=>'0.01','min'=>'0']], 'search'=>[],
        'display'=>['venta_id','producto_id','cantidad','subtotal'],'title'=>'id'],
    'detalleventas' => ['label'=>'Detalle de ventas (vista)','singular'=>'Resultado','icon'=>'▤','read_only'=>true,'columns'=>[
        'nombre'=>['Producto','text',''],'descripcion'=>['Descripción','text',''],'Categoria'=>['Categoría','text',''],
        'fecha_de_venta'=>['Fecha de venta','date',''],'total'=>['Total de venta','number',''],'metodo_pago'=>['Método de pago','text',''],
        'cantidad'=>['Cantidad','number',''],'precio_unitario'=>['Precio unitario','number',''],'subtotal'=>['Subtotal','number',''],
        'cliente'=>['Cliente','text','']], 'search'=>['nombre','descripcion','Categoria','metodo_pago','cliente'],
        'display'=>['nombre','descripcion','Categoria','fecha_de_venta','total','metodo_pago','cantidad','precio_unitario','subtotal','cliente'],'title'=>'nombre'],
];
$relations = ['categoria_id'=>'categorias','proveedor_id'=>'proveedores','cliente_id'=>'clientes','venta_id'=>'ventas','producto_id'=>'productos'];

function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function db(): PDO {
    global $config;
    static $pdo = null;
    if (!$pdo) $pdo = new PDO('mysql:host='.$config['host'].';dbname='.$config['name'].';charset='.$config['charset'], $config['user'], $config['pass'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    return $pdo;
}
function label_value(string $entity, string $key, $value): string {
    global $relations;
    if (isset($relations[$key]) && $value !== null && $value !== '') {
        $table = $relations[$key];
        $title = $table === 'ventas' ? "CONCAT('Venta #', id)" : '`'.(in_array($table,['productos','categorias','clientes','proveedores'],true) ? 'nombre' : 'id').'`';
        $stmt = db()->prepare("SELECT $title AS title FROM `$table` WHERE id = ?"); $stmt->execute([$value]);
        return (string)($stmt->fetchColumn() ?: '#'.$value);
    }
    if ($key === 'total' || $key === 'subtotal' || $key === 'precio' || $key === 'precio_unitario') return '$'.number_format((float)$value, 2, ',', '.');
    return (string)($value ?? '—');
}
function csrf(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function redirect_to(string $entity, string $message, string $type='success'): void {
    $_SESSION['flash'] = [$message,$type]; header('Location: ?entity='.urlencode($entity)); exit;
}

$entity = isset($_GET['entity']) && isset($entities[$_GET['entity']]) ? $_GET['entity'] : 'dashboard';
$action = $_GET['action'] ?? 'list';
$error = null; $rows = []; $viewRows = []; $record = []; $counts = []; $dbConnected = false;
try {
    db();
    $dbConnected = true;
    if ($entity === 'dashboard') {
        foreach ($entities as $key=>$meta) $counts[$key] = (int)db()->query("SELECT COUNT(*) FROM `$key`")->fetchColumn();
        $rows = db()->query('SELECT v.id,v.fecha,v.total,v.metodo_pago,c.nombre AS cliente FROM ventas v LEFT JOIN clientes c ON c.id=v.cliente_id ORDER BY v.id DESC LIMIT 6')->fetchAll();
        $viewRows = db()->query('SELECT * FROM detalleventas ORDER BY fecha_de_venta DESC, nombre LIMIT 6')->fetchAll();
    } elseif (!isset($entities[$entity])) { $entity='dashboard'; }
    else {
        $meta = $entities[$entity];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!hash_equals(csrf(), $_POST['csrf'] ?? '')) throw new RuntimeException('La sesión del formulario venció. Recargá la página e intentá de nuevo.');
            $id = (int)($_POST['id'] ?? 0);
            if (!empty($meta['read_only'])) throw new RuntimeException('Esta vista es de solo lectura.');
            if (($_POST['intent'] ?? '') === 'delete') {
                if (!$id) throw new RuntimeException('No se encontró el registro.');
                $stmt=db()->prepare("DELETE FROM `$entity` WHERE id=?"); $stmt->execute([$id]);
                redirect_to($entity, $meta['singular'].' eliminado correctamente.');
            }
            $data=[];
            foreach ($meta['columns'] as $field=>$spec) {
                if ($field==='id') continue;
                $value=trim((string)($_POST[$field] ?? ''));
                if (($spec[2] ?? '')==='required' && $value==='') throw new RuntimeException('El campo «'.$spec[0].'» es obligatorio.');
                if ($value!=='' && $spec[1]==='email' && !filter_var($value,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Ingresá un email válido.');
                if (strpos($spec[1],'relation:')===0 && $value!=='' && !ctype_digit($value)) throw new RuntimeException('Seleccioná una opción válida para «'.$spec[0].'».');
                if ($value==='') $value=null;
                $data[$field]=$value;
            }
            if ($entity==='detalle_ventas' && isset($data['cantidad'],$data['precio_unitario'])) $data['subtotal']=number_format((float)$data['cantidad']*(float)$data['precio_unitario'],2,'.','');
            if ($id) {
                $sets=[]; foreach(array_keys($data) as $field) $sets[]="`$field`=?";
                $stmt=db()->prepare("UPDATE `$entity` SET ".implode(',',$sets).' WHERE id=?'); $stmt->execute(array_merge(array_values($data),[$id]));
                redirect_to($entity,$meta['singular'].' actualizado correctamente.');
            } else {
                $fields=array_keys($data); $stmt=db()->prepare("INSERT INTO `$entity` (`".implode('`,`',$fields).'`) VALUES ('.implode(',',array_fill(0,count($fields),'?')).')');
                $stmt->execute(array_values($data)); redirect_to($entity,$meta['singular'].' creado correctamente.');
            }
        }
        if (in_array($action,['new','edit'],true)) {
            $record=[]; $id=(int)($_GET['id'] ?? 0);
            if ($action==='edit') { $stmt=db()->prepare("SELECT * FROM `$entity` WHERE id=?"); $stmt->execute([$id]); $record=$stmt->fetch(); if (!$record) { $action='list'; $_SESSION['flash']=['No se encontró el registro solicitado.','error']; } }
        } else {
            $search=trim((string)($_GET['q'] ?? '')); $sql="SELECT * FROM `$entity`"; $params=[];
            if ($search!=='' && $meta['search']) { $clauses=[]; foreach($meta['search'] as $field) { $clauses[]="`$field` LIKE ?"; $params[]='%'.$search.'%'; } $sql.=' WHERE '.implode(' OR ',$clauses); }
            $sort = !empty($meta['read_only']) ? '`fecha_de_venta` DESC, `nombre`' : '`id` DESC';
            $sql.=' ORDER BY '.$sort;
            if (($meta['read_only']??false) && $action==='export') {
                $stmt=db()->prepare($sql); $stmt->execute($params); $exportRows=$stmt->fetchAll();
                header('Content-Type: text/csv; charset=UTF-8');
                header('Content-Disposition: attachment; filename="techstore-detalleventas-'.date('Y-m-d').'.csv"');
                echo "\xEF\xBB\xBF"; $out=fopen('php://output','w');
                fputcsv($out,array_map(function($field) use ($meta){return $meta['columns'][$field][0];},array_keys($meta['columns'])),';');
                foreach($exportRows as $exportRow) fputcsv($out,array_values($exportRow),';');
                fclose($out); exit;
            }
            $stmt=db()->prepare($sql); $stmt->execute($params); $rows=$stmt->fetchAll();
        }
    }
} catch (Throwable $ex) {
    if ($ex instanceof PDOException) {
        $error = 'No se pudo conectar a la base de datos. Configurá DB_HOST, DB_NAME, DB_USER y DB_PASS en Render, y verificá que la base permita conexiones desde Render.';
        if ((int)$ex->getCode()===23000) $error='No se puede eliminar este registro porque está relacionado con otros datos.';
        elseif ((string)$ex->getCode()==='42S02') $error='La base de datos se conectó, pero faltan las tablas de TechStore. Importá el esquema SQL.';
    } else {
        $error = $ex->getMessage();
    }
}
$flash=$_SESSION['flash'] ?? null; unset($_SESSION['flash']);
?>
<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>TechStore · Gestión</title><link rel="stylesheet" href="assets/style.css"></head>
<body>
<aside class="sidebar">
    <a class="brand" href="?"><span class="brand-mark">T</span><span>techstore<small>ADMINISTRACIÓN</small></span></a>
    <div class="nav-label">MENÚ PRINCIPAL</div>
    <nav><a class="nav-link <?= $entity==='dashboard'?'active':'' ?>" href="?"><span>⌂</span>Resumen</a>
    <?php foreach($entities as $key=>$item): ?><a class="nav-link <?= $entity===$key?'active':'' ?>" href="?entity=<?= e($key) ?>"><span><?= e($item['icon']) ?></span><?= e($item['label']) ?></a><?php endforeach; ?></nav>
    <div class="sidebar-bottom"><span class="status-dot <?= $dbConnected ? '' : 'offline' ?>"></span><?= $dbConnected ? 'Base de datos conectada' : 'Base de datos sin configurar' ?></div>
</aside>
<main class="main">
    <header class="topbar"><div><span class="eyebrow">PANEL DE CONTROL</span><h1><?= $entity==='dashboard'?'Resumen':e($entities[$entity]['label']) ?></h1></div><div class="avatar">TS</div></header>
    <section class="content">
    <?php if($error): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?>
    <?php if($flash): ?><div class="alert <?= e($flash[1]) ?>"><?= e($flash[0]) ?></div><?php endif; ?>
    <?php if($entity==='dashboard'): ?>
        <div class="welcome"><div><span class="eyebrow light">GESTIÓN COMERCIAL</span><h2>Todo tu negocio,<br>en un solo lugar.</h2><p>Administrá productos, clientes, proveedores y ventas.</p></div><div class="welcome-icon">✦</div></div>
        <div class="section-head"><div><h2>Resumen general</h2><p>Estado actual de tu tienda</p></div></div>
        <div class="stats-grid"><?php foreach($entities as $key=>$item): ?><a class="stat-card" href="?entity=<?= e($key) ?>"><div class="stat-top"><span><?= e($item['label']) ?></span><span class="stat-icon"><?= e($item['icon']) ?></span></div><strong><?= (int)($counts[$key]??0) ?></strong><small>registros en total <span>↗</span></small></a><?php endforeach; ?></div>
        <div class="panel"><div class="panel-head"><div><h2>Últimas ventas</h2><p>Movimientos recientes de la tienda</p></div><a class="text-link" href="?entity=ventas">Ver ventas →</a></div>
            <?php if($rows): ?><div class="table-wrap"><table><thead><tr><th>VENTA</th><th>CLIENTE</th><th>FECHA</th><th>MÉTODO</th><th class="right">TOTAL</th></tr></thead><tbody><?php foreach($rows as $row): ?><tr><td><span class="id-tag">#<?= e($row['id']) ?></span></td><td class="strong"><?= e($row['cliente']??'—') ?></td><td><?= e($row['fecha']??'—') ?></td><td><?= e($row['metodo_pago']??'—') ?></td><td class="right strong"><?= e(label_value('ventas','total',$row['total'])) ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="empty">Todavía no hay ventas registradas.</div><?php endif; ?>
        </div>
        <div class="panel dashboard-view"><div class="panel-head"><div><h2>Vista detalleventas</h2><p>Consulta consolidada de productos, ventas y clientes</p></div><a class="text-link" href="?entity=detalleventas">Ver listado completo →</a></div>
            <?php if($viewRows): ?><div class="table-wrap"><table><thead><tr><th>PRODUCTO</th><th>CATEGORÍA</th><th>FECHA</th><th>CLIENTE</th><th>CANTIDAD</th><th class="right">SUBTOTAL</th></tr></thead><tbody><?php foreach($viewRows as $viewRow): ?><tr><td class="strong"><?= e($viewRow['nombre']) ?></td><td><?= e($viewRow['Categoria']) ?></td><td><?= e($viewRow['fecha_de_venta']??'—') ?></td><td><?= e($viewRow['cliente']) ?></td><td><?= e($viewRow['cantidad']) ?></td><td class="right strong"><?= e(label_value('detalleventas','subtotal',$viewRow['subtotal'])) ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="empty">La vista no contiene resultados todavía.</div><?php endif; ?>
        </div>
    <?php else: $meta=$entities[$entity]; ?>
        <?php if(in_array($action,['new','edit'],true) && (isset($record)||$action==='new')): ?>
            <div class="back-row"><a href="?entity=<?= e($entity) ?>">← Volver a <?= e($meta['label']) ?></a></div>
            <div class="panel form-panel"><div class="panel-head"><div><h2><?= $action==='new'?'Nuevo '.$meta['singular']:'Editar '.$meta['singular'] ?></h2><p>Completá los datos y guardá los cambios.</p></div></div>
            <form method="post" class="form-grid"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="id" value="<?= e($record['id']??'') ?>">
            <?php foreach($meta['columns'] as $field=>$spec): if($field==='id') continue; $type=$spec[1]; $value=$record[$field]??''; $required=($spec[2]??'')==='required'; ?>
                <label class="field <?= $type==='textarea'?'wide':'' ?>"><span><?= e($spec[0]) ?><?= $required?' <b>*</b>':'' ?></span>
                <?php if(strpos($type,'relation:')===0): $table=substr($type,9); $title=$table==='ventas'?"CONCAT('Venta #',id)":"`nombre`"; $opts=db()->query("SELECT id,$title AS title FROM `$table` ORDER BY id DESC")->fetchAll(); ?>
                    <select name="<?= e($field) ?>" <?= $required?'required':'' ?>><option value="">Seleccionar…</option><?php foreach($opts as $opt): ?><option value="<?= e($opt['id']) ?>" <?= (string)$value===(string)$opt['id']?'selected':'' ?>><?= e($opt['title']) ?> · #<?= e($opt['id']) ?></option><?php endforeach; ?></select>
                <?php elseif($type==='textarea'): ?><textarea name="<?= e($field) ?>" rows="3"><?= e($value) ?></textarea>
                <?php else: ?><input type="<?= e($type) ?>" name="<?= e($field) ?>" value="<?= e($value) ?>" <?= $required?'required':'' ?> <?= isset($spec['step'])?'step="'.e($spec['step']).'"':'' ?> <?= isset($spec['min'])?'min="'.e($spec['min']).'"':'' ?>><?php endif; ?>
                </label>
            <?php endforeach; ?><div class="form-actions"><a class="button secondary" href="?entity=<?= e($entity) ?>">Cancelar</a><button class="button primary" type="submit">Guardar <?= e($meta['singular']) ?></button></div></form></div>
        <?php else: ?>
            <div class="section-head list-head"><div><h2><?= e($meta['label']) ?></h2><p><?= count($rows) ?> registro<?= count($rows)===1?'':'s' ?><?= isset($_GET['q'])&&$_GET['q']!==''?' encontrados':'' ?><?= !empty($meta['read_only'])?' · Solo lectura':'' ?></p></div><?php if(!empty($meta['read_only'])): ?><a class="button primary" href="?entity=<?= e($entity) ?>&action=export<?= !empty($_GET['q'])?'&q='.urlencode($_GET['q']):'' ?>">↓ Descargar para Excel</a><?php else: ?><a class="button primary" href="?entity=<?= e($entity) ?>&action=new">＋ Nuevo <?= e($meta['singular']) ?></a><?php endif; ?></div>
            <form class="search-form" method="get"><input type="hidden" name="entity" value="<?= e($entity) ?>"><span>⌕</span><input type="search" name="q" placeholder="Buscar <?= e(mb_strtolower($meta['label'],'UTF-8')) ?>…" value="<?= e($_GET['q']??'') ?>"><button type="submit">Buscar</button><?php if(!empty($_GET['q'])): ?><a href="?entity=<?= e($entity) ?>">Limpiar</a><?php endif; ?></form>
            <div class="panel table-panel"><div class="table-wrap"><table><thead><tr><?php foreach($meta['display'] as $field): ?><th><?= e($meta['columns'][$field][0]) ?></th><?php endforeach; ?><th class="right">ACCIONES</th></tr></thead><tbody>
            <?php foreach($rows as $row): ?><tr><?php foreach($meta['display'] as $field): ?><td class="<?= $field===$meta['title']?'strong':'' ?>"><?php if($field==='stock' && (int)$row[$field]<5): ?><span class="stock low"><?= e($row[$field]??'—') ?></span><?php elseif($field==='stock'): ?><span class="stock"><?= e($row[$field]??'—') ?></span><?php else: ?><?= e(label_value($entity,$field,$row[$field]??null)) ?><?php endif; ?></td><?php endforeach; ?><td><?php if(empty($meta['read_only'])): ?><div class="row-actions"><a href="?entity=<?= e($entity) ?>&action=edit&id=<?= e($row['id']) ?>" class="icon-button" title="Editar">✎</a><form method="post" onsubmit="return confirm('¿Eliminar este registro? Esta acción no se puede deshacer.')"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="id" value="<?= e($row['id']) ?>"><input type="hidden" name="intent" value="delete"><button class="icon-button delete" title="Eliminar" type="submit">⌫</button></form></div><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; ?>
            </tbody></table><?php if(!$rows): ?><div class="empty"><span>⌕</span><strong>No hay registros para mostrar</strong><p>Probá con otra búsqueda o creá un registro nuevo.</p></div><?php endif; ?></div></div>
        <?php endif; ?>
    <?php endif; ?>
    </section><footer>TechStore Administración <span>·</span> Gestión local de datos</footer>
</main>
</body></html>
