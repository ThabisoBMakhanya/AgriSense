<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function journal_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function journal_string(array $record, string $field, int $limit, bool $required = false): string
{
    $value = trim((string)($record[$field] ?? ''));
    if (strlen($value) > $limit || ($required && $value === '')) journal_response(['error' => 'Invalid ' . $field . '.'], 422);
    return $value;
}

function journal_date(array $record, string $field): string
{
    $value = trim((string)($record[$field] ?? ''));
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) journal_response(['error' => 'Enter a valid date.'], 422);
    return $value;
}

function journal_decimal(array $record, string $field): float
{
    $value = $record[$field] ?? 0;
    if (!is_numeric($value) || (float)$value < 0 || (float)$value > 1000000000) journal_response(['error' => 'Enter a valid ' . $field . '.'], 422);
    return (float)$value;
}

function journal_rows(PDO $db, string $sql, string $owner): array
{
    $statement = $db->prepare($sql);
    $statement->execute([$owner]);
    return $statement->fetchAll();
}

$owner = strtolower((string)(current_user()['email'] ?? ''));
if ($owner === '') journal_response(['error' => 'Sign in again to use your farm journal.'], 401);
$db = db();
$driver = db_driver();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$now = date('Y-m-d H:i:s');

try {
    if ($method === 'GET') {
        $tasks = journal_rows($db, 'SELECT id,client_id,title,record_scope,category,due_date,repeat_days,note,completed_at,created_at,updated_at FROM farm_tasks WHERE farmer_email = ? ORDER BY due_date DESC', $owner);
        $records = journal_rows($db, 'SELECT id,client_id,record_type,record_scope,item,amount,quantity,unit,entry_date,note,created_at,updated_at FROM farm_records WHERE farmer_email = ? ORDER BY entry_date DESC', $owner);
        $photoField = $driver === 'pgsql' ? "encode(photo_data, 'base64')" : 'TO_BASE64(photo_data)';
        $cases = journal_rows($db, "SELECT id,client_id,subject,subject_type,severity,symptoms,{$photoField} AS photo_base64,photo_mime,photo_name,entry_date,created_at,updated_at FROM field_cases WHERE farmer_email = ? ORDER BY entry_date DESC", $owner);
        $animals = journal_rows($db, 'SELECT id,client_id,animal_group,activity,quantity,unit,entry_date,note,created_at,updated_at FROM animal_records WHERE farmer_email = ? ORDER BY entry_date DESC', $owner);
        $inventory = journal_rows($db, 'SELECT id,client_id,input_name,input_type,quantity,unit,minimum_quantity,expiry_date,note,created_at,updated_at FROM farm_inventory WHERE farmer_email = ? ORDER BY input_name', $owner);
        $stockMoves = journal_rows($db, 'SELECT id,client_id,stock_client_id,linked_entry_client_id,quantity_delta,entry_date,created_at,updated_at FROM farm_stock_movements WHERE farmer_email = ? ORDER BY entry_date DESC', $owner);
        $harvests = journal_rows($db, 'SELECT id,client_id,field_name,crop,harvest_date,quantity,unit,grade,sale_amount,note,created_at,updated_at FROM field_harvests WHERE farmer_email = ? ORDER BY harvest_date DESC', $owner);
        $preferencesQuery = $db->prepare('SELECT settings FROM farm_journal_preferences WHERE farmer_email = ?');
        $preferencesQuery->execute([$owner]);
        $preferences = $preferencesQuery->fetchColumn();
        if (is_string($preferences)) $preferences = json_decode($preferences, true) ?: [];
        $result = [];
        foreach ($tasks as $row) $result[] = ['id'=>(int)$row['id'],'clientId'=>$row['client_id'],'type'=>'task','title'=>$row['title'],'scope'=>$row['record_scope'],'category'=>$row['category'],'dueDate'=>$row['due_date'],'repeatDays'=>(int)$row['repeat_days'],'note'=>$row['note'],'completedAt'=>$row['completed_at'],'createdAt'=>$row['created_at'],'updatedAt'=>$row['updated_at'],'syncStatus'=>'synced'];
        foreach ($records as $row) $result[] = ['id'=>(int)$row['id'],'clientId'=>$row['client_id'],'type'=>'finance','recordType'=>$row['record_type'],'scope'=>$row['record_scope'],'item'=>$row['item'],'amount'=>(float)$row['amount'],'quantity'=>(float)$row['quantity'],'unit'=>$row['unit'],'entryDate'=>$row['entry_date'],'note'=>$row['note'],'createdAt'=>$row['created_at'],'updatedAt'=>$row['updated_at'],'syncStatus'=>'synced'];
        foreach ($cases as $row) $result[] = ['id'=>(int)$row['id'],'clientId'=>$row['client_id'],'type'=>'case','subject'=>$row['subject'],'subjectType'=>$row['subject_type'],'severity'=>$row['severity'],'symptoms'=>$row['symptoms'],'photoBase64'=>preg_replace('/\s+/', '', (string)($row['photo_base64'] ?? '')),'photoMime'=>$row['photo_mime'],'photoName'=>$row['photo_name'],'entryDate'=>$row['entry_date'],'createdAt'=>$row['created_at'],'updatedAt'=>$row['updated_at'],'syncStatus'=>'synced'];
        foreach ($animals as $row) $result[] = ['id'=>(int)$row['id'],'clientId'=>$row['client_id'],'type'=>'animal','group'=>$row['animal_group'],'activity'=>$row['activity'],'quantity'=>(float)$row['quantity'],'unit'=>$row['unit'],'entryDate'=>$row['entry_date'],'note'=>$row['note'],'createdAt'=>$row['created_at'],'updatedAt'=>$row['updated_at'],'syncStatus'=>'synced'];
        foreach ($inventory as $row) $result[] = ['id'=>(int)$row['id'],'clientId'=>$row['client_id'],'type'=>'inventory','name'=>$row['input_name'],'inputType'=>$row['input_type'],'quantity'=>(float)$row['quantity'],'unit'=>$row['unit'],'minimumQuantity'=>(float)$row['minimum_quantity'],'expiryDate'=>$row['expiry_date'],'note'=>$row['note'],'createdAt'=>$row['created_at'],'updatedAt'=>$row['updated_at'],'syncStatus'=>'synced'];
        foreach ($stockMoves as $row) $result[] = ['id'=>(int)$row['id'],'clientId'=>$row['client_id'],'type'=>'stock','stockClientId'=>$row['stock_client_id'],'linkedEntryClientId'=>$row['linked_entry_client_id'],'quantityDelta'=>(float)$row['quantity_delta'],'entryDate'=>$row['entry_date'],'createdAt'=>$row['created_at'],'updatedAt'=>$row['updated_at'],'syncStatus'=>'synced'];
        foreach ($harvests as $row) $result[] = ['id'=>(int)$row['id'],'clientId'=>$row['client_id'],'type'=>'yield','fieldName'=>$row['field_name'],'crop'=>$row['crop'],'harvestDate'=>$row['harvest_date'],'quantity'=>(float)$row['quantity'],'unit'=>$row['unit'],'grade'=>$row['grade'],'saleAmount'=>(float)$row['sale_amount'],'note'=>$row['note'],'createdAt'=>$row['created_at'],'updatedAt'=>$row['updated_at'],'syncStatus'=>'synced'];
        journal_response(['records'=>$result,'preferences'=>$preferences ?: null]);
    }

    if (!in_array($method, ['POST', 'DELETE'], true)) journal_response(['error'=>'Method not allowed.'], 405);
    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    $requestHost = (string)parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
    if ($origin !== '' && strcasecmp((string)parse_url($origin, PHP_URL_HOST), $requestHost) !== 0) journal_response(['error'=>'Cross-origin journal requests are not allowed.'], 403);
    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) journal_response(['error'=>'Invalid request data.'], 400);
    $action = (string)($input['action'] ?? 'save');
    $entity = (string)($input['entity'] ?? '');

    if ($action === 'savePreferences') {
        $allowed = ['task','finance','yield','case','inventory','animal'];
        $settings = [];
        foreach ($allowed as $key) $settings[$key] = filter_var($input['settings'][$key] ?? true, FILTER_VALIDATE_BOOLEAN);
        $settingsJson = json_encode($settings, JSON_THROW_ON_ERROR);
        $sql = 'INSERT INTO farm_journal_preferences (farmer_email,settings) VALUES (?,CAST(? AS JSONB)) ON CONFLICT (farmer_email) DO UPDATE SET settings=EXCLUDED.settings,updated_at=NOW()';
        if ($driver !== 'pgsql') $sql = 'INSERT INTO farm_journal_preferences (farmer_email,settings) VALUES (?,?) ON DUPLICATE KEY UPDATE settings=VALUES(settings),updated_at=CURRENT_TIMESTAMP(6)';
        $statement = $db->prepare($sql);
        $statement->execute([$owner, $settingsJson]);
        journal_response(['ok'=>true,'preferences'=>$settings]);
    }

    if ($action === 'delete') {
        $clientId = (string)($input['clientId'] ?? '');
        if (!preg_match('/^[a-f0-9-]{36}$/i', $clientId)) journal_response(['error'=>'Invalid record identifier.'], 422);
        $table = ['task'=>'farm_tasks','finance'=>'farm_records','case'=>'field_cases','animal'=>'animal_records','inventory'=>'farm_inventory','yield'=>'field_harvests','stock'=>'farm_stock_movements'][$entity] ?? null;
        if ($table === null) journal_response(['error'=>'Invalid record type.'], 422);
        $statement = $db->prepare("DELETE FROM {$table} WHERE farmer_email = ? AND client_id = ?");
        $statement->execute([$owner, $clientId]);
        if (in_array($entity, ['task','animal'], true)) { $cleanup = $db->prepare('DELETE FROM farm_stock_movements WHERE farmer_email = ? AND linked_entry_client_id = ?'); $cleanup->execute([$owner,$clientId]); }
        if ($entity === 'inventory') { $cleanup = $db->prepare('DELETE FROM farm_stock_movements WHERE farmer_email = ? AND stock_client_id = ?'); $cleanup->execute([$owner,$clientId]); }
        journal_response(['ok'=>true]);
    }

    if ($action !== 'save') journal_response(['error'=>'Invalid action.'], 422);
    $record = $input['record'] ?? null;
    if (!is_array($record)) journal_response(['error'=>'Missing record.'], 422);
    $clientId = (string)($record['clientId'] ?? '');
    if (!preg_match('/^[a-f0-9-]{36}$/i', $clientId)) journal_response(['error'=>'Invalid record identifier.'], 422);

    $entityTables = ['task'=>'farm_tasks','finance'=>'farm_records','case'=>'field_cases','animal'=>'animal_records','inventory'=>'farm_inventory','yield'=>'field_harvests','stock'=>'farm_stock_movements'];
    $table = $entityTables[$entity] ?? null;
    if ($table === null) journal_response(['error'=>'Invalid record type.'], 422);
    $db->beginTransaction();
    $existing = $db->prepare("SELECT updated_at FROM {$table} WHERE farmer_email = ? AND client_id = ? FOR UPDATE");
    $existing->execute([$owner, $clientId]);
    $serverUpdatedAt = $existing->fetchColumn();
    if ($serverUpdatedAt !== false && (string)($record['updatedAt'] ?? '') !== (string)$serverUpdatedAt) {
        $db->rollBack();
        journal_response(['error'=>'This entry changed in another session. Choose which version to keep.', 'conflict'=>true, 'updatedAt'=>(string)$serverUpdatedAt], 409);
    }

    if ($entity === 'task') {
        $title = journal_string($record, 'title', 120, true);
        $scope = (string)($record['scope'] ?? 'crops');
        if (!in_array($scope, ['crops','animals'], true)) journal_response(['error'=>'Choose crops and vegetables or animals.'], 422);
        $category = journal_string($record, 'category', 60, true);
        $dueDate = journal_date($record, 'dueDate');
        $repeatDays = filter_var($record['repeatDays'] ?? 0, FILTER_VALIDATE_INT);
        if ($repeatDays === false || $repeatDays < 0 || $repeatDays > 365) journal_response(['error'=>'Repeat days must be between 0 and 365.'], 422);
        $note = journal_string($record, 'note', 4000);
        $completedAt = !empty($record['completedAt']) ? $now : null;
        $sql = 'INSERT INTO farm_tasks (farmer_email,client_id,title,record_scope,category,due_date,repeat_days,note,completed_at) VALUES (?,?,?,?,?,?,?,?,?) ON CONFLICT (farmer_email,client_id) DO UPDATE SET title=EXCLUDED.title,record_scope=EXCLUDED.record_scope,category=EXCLUDED.category,due_date=EXCLUDED.due_date,repeat_days=EXCLUDED.repeat_days,note=EXCLUDED.note,completed_at=EXCLUDED.completed_at,updated_at=NOW()';
        if ($driver !== 'pgsql') $sql = 'INSERT INTO farm_tasks (farmer_email,client_id,title,record_scope,category,due_date,repeat_days,note,completed_at) VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),record_scope=VALUES(record_scope),category=VALUES(category),due_date=VALUES(due_date),repeat_days=VALUES(repeat_days),note=VALUES(note),completed_at=VALUES(completed_at),updated_at=CURRENT_TIMESTAMP(6)';
        $statement = $db->prepare($sql); $statement->execute([$owner,$clientId,$title,$scope,$category,$dueDate,$repeatDays,$note,$completedAt]);
    } elseif ($entity === 'finance') {
        $recordType = (string)($record['recordType'] ?? '');
        if (!in_array($recordType, ['expense','sale','harvest'], true)) journal_response(['error'=>'Invalid farm record type.'], 422);
        $scope = (string)($record['scope'] ?? 'crops');
        if (!in_array($scope, ['crops','animals'], true)) journal_response(['error'=>'Choose crops and vegetables or animals.'], 422);
        $item = journal_string($record, 'item', 150, true);
        $amount = journal_decimal($record, 'amount');
        $quantity = journal_decimal($record, 'quantity');
        $unit = journal_string($record, 'unit', 30);
        $entryDate = journal_date($record, 'entryDate');
        $note = journal_string($record, 'note', 4000);
        $sql = 'INSERT INTO farm_records (farmer_email,client_id,record_type,record_scope,item,amount,quantity,unit,entry_date,note) VALUES (?,?,?,?,?,?,?,?,?,?) ON CONFLICT (farmer_email,client_id) DO UPDATE SET record_type=EXCLUDED.record_type,record_scope=EXCLUDED.record_scope,item=EXCLUDED.item,amount=EXCLUDED.amount,quantity=EXCLUDED.quantity,unit=EXCLUDED.unit,entry_date=EXCLUDED.entry_date,note=EXCLUDED.note,updated_at=NOW()';
        if ($driver !== 'pgsql') $sql = 'INSERT INTO farm_records (farmer_email,client_id,record_type,record_scope,item,amount,quantity,unit,entry_date,note) VALUES (?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE record_type=VALUES(record_type),record_scope=VALUES(record_scope),item=VALUES(item),amount=VALUES(amount),quantity=VALUES(quantity),unit=VALUES(unit),entry_date=VALUES(entry_date),note=VALUES(note),updated_at=CURRENT_TIMESTAMP(6)';
        $statement = $db->prepare($sql); $statement->execute([$owner,$clientId,$recordType,$scope,$item,$amount,$quantity,$unit,$entryDate,$note]);
    } elseif ($entity === 'case') {
        $subject = journal_string($record, 'subject', 150, true);
        $subjectType = (string)($record['subjectType'] ?? 'crops');
        if (!in_array($subjectType, ['crops','animals'], true)) journal_response(['error'=>'Choose crops and vegetables or animals.'], 422);
        $severity = (string)($record['severity'] ?? 'Watching');
        if (!in_array($severity, ['Watching','Moderate','Urgent'], true)) journal_response(['error'=>'Invalid severity.'], 422);
        $symptoms = journal_string($record, 'symptoms', 6000, true);
        $photoBase64 = (string)($record['photoBase64'] ?? '');
        if (strlen($photoBase64) > 950000) journal_response(['error'=>'Photo must be smaller than 700 KB.'], 413);
        $photoBytes = $photoBase64 !== '' ? base64_decode($photoBase64, true) : null;
        if ($photoBase64 !== '' && $photoBytes === false) journal_response(['error'=>'Invalid photo data.'], 422);
        $photoMime = $photoBytes !== null ? journal_string($record, 'photoMime', 80, true) : null;
        if ($photoMime !== null && !in_array($photoMime, ['image/jpeg','image/png','image/webp'], true)) journal_response(['error'=>'Use a JPEG, PNG, or WebP photo.'], 422);
        $photoName = $photoBytes !== null ? journal_string($record, 'photoName', 200) : null;
        $entryDate = journal_date($record, 'entryDate');
        $photoParam = $photoBase64 !== '' ? $photoBase64 : null;
        $photoSql = $driver === 'pgsql' ? "decode(?, 'base64')" : 'FROM_BASE64(?)';
        if ($driver === 'pgsql') {
            $sql = "INSERT INTO field_cases (farmer_email,client_id,subject,subject_type,severity,symptoms,photo_data,photo_mime,photo_name,entry_date) VALUES (?,?,?,?,?,?,{$photoSql},?,?,?) ON CONFLICT (farmer_email,client_id) DO UPDATE SET subject=EXCLUDED.subject,subject_type=EXCLUDED.subject_type,severity=EXCLUDED.severity,symptoms=EXCLUDED.symptoms,photo_data=EXCLUDED.photo_data,photo_mime=EXCLUDED.photo_mime,photo_name=EXCLUDED.photo_name,entry_date=EXCLUDED.entry_date,updated_at=NOW()";
            $statement = $db->prepare($sql); $statement->execute([$owner,$clientId,$subject,$subjectType,$severity,$symptoms,$photoParam,$photoMime,$photoName,$entryDate]);
        } else {
            $sql = 'INSERT INTO field_cases (farmer_email,client_id,subject,subject_type,severity,symptoms,photo_data,photo_mime,photo_name,entry_date) VALUES (?,?,?,?,?,?'.($photoParam === null ? ',NULL' : ','.$photoSql).',?,?,?) ON DUPLICATE KEY UPDATE subject=VALUES(subject),subject_type=VALUES(subject_type),severity=VALUES(severity),symptoms=VALUES(symptoms),photo_data=VALUES(photo_data),photo_mime=VALUES(photo_mime),photo_name=VALUES(photo_name),entry_date=VALUES(entry_date),updated_at=CURRENT_TIMESTAMP(6)';
            $params = [$owner,$clientId,$subject,$subjectType,$severity,$symptoms];
            if ($photoParam !== null) $params[] = $photoParam;
            array_push($params,$photoMime,$photoName,$entryDate);
        $statement = $db->prepare($sql); $statement->execute($params);
        }
    } elseif ($entity === 'animal') {
        $group = journal_string($record, 'group', 150, true);
        $activity = journal_string($record, 'activity', 60, true);
        $quantity = journal_decimal($record, 'quantity');
        $unit = journal_string($record, 'unit', 30);
        $entryDate = journal_date($record, 'entryDate');
        $note = journal_string($record, 'note', 4000);
        $sql = 'INSERT INTO animal_records (farmer_email,client_id,animal_group,activity,quantity,unit,entry_date,note) VALUES (?,?,?,?,?,?,?,?) ON CONFLICT (farmer_email,client_id) DO UPDATE SET animal_group=EXCLUDED.animal_group,activity=EXCLUDED.activity,quantity=EXCLUDED.quantity,unit=EXCLUDED.unit,entry_date=EXCLUDED.entry_date,note=EXCLUDED.note,updated_at=NOW()';
        if ($driver !== 'pgsql') $sql = 'INSERT INTO animal_records (farmer_email,client_id,animal_group,activity,quantity,unit,entry_date,note) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE animal_group=VALUES(animal_group),activity=VALUES(activity),quantity=VALUES(quantity),unit=VALUES(unit),entry_date=VALUES(entry_date),note=VALUES(note),updated_at=CURRENT_TIMESTAMP(6)';
        $statement = $db->prepare($sql); $statement->execute([$owner,$clientId,$group,$activity,$quantity,$unit,$entryDate,$note]);
    } elseif ($entity === 'inventory') {
        $name = journal_string($record, 'name', 150, true);
        $inputType = (string)($record['inputType'] ?? '');
        if (!in_array($inputType, ['seed','fertilizer','chemical','feed','fuel','other'], true)) journal_response(['error'=>'Choose a valid input category.'], 422);
        $quantity = journal_decimal($record, 'quantity');
        $unit = journal_string($record, 'unit', 30, true);
        $minimumQuantity = journal_decimal($record, 'minimumQuantity');
        $expiryDate = !empty($record['expiryDate']) ? journal_date($record, 'expiryDate') : null;
        $note = journal_string($record, 'note', 4000);
        $sql = 'INSERT INTO farm_inventory (farmer_email,client_id,input_name,input_type,quantity,unit,minimum_quantity,expiry_date,note) VALUES (?,?,?,?,?,?,?,?,?) ON CONFLICT (farmer_email,client_id) DO UPDATE SET input_name=EXCLUDED.input_name,input_type=EXCLUDED.input_type,quantity=EXCLUDED.quantity,unit=EXCLUDED.unit,minimum_quantity=EXCLUDED.minimum_quantity,expiry_date=EXCLUDED.expiry_date,note=EXCLUDED.note,updated_at=NOW()';
        if ($driver !== 'pgsql') $sql = 'INSERT INTO farm_inventory (farmer_email,client_id,input_name,input_type,quantity,unit,minimum_quantity,expiry_date,note) VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE input_name=VALUES(input_name),input_type=VALUES(input_type),quantity=VALUES(quantity),unit=VALUES(unit),minimum_quantity=VALUES(minimum_quantity),expiry_date=VALUES(expiry_date),note=VALUES(note),updated_at=CURRENT_TIMESTAMP(6)';
        $statement = $db->prepare($sql); $statement->execute([$owner,$clientId,$name,$inputType,$quantity,$unit,$minimumQuantity,$expiryDate,$note]);
    } elseif ($entity === 'stock') {
        $stockClientId = (string)($record['stockClientId'] ?? '');
        $linkedEntryClientId = (string)($record['linkedEntryClientId'] ?? '');
        if (!preg_match('/^[a-f0-9-]{36}$/i', $stockClientId) || !preg_match('/^[a-f0-9-]{36}$/i', $linkedEntryClientId)) journal_response(['error'=>'Choose a valid supply and linked entry.'], 422);
        $stockCheck = $db->prepare('SELECT id FROM farm_inventory WHERE farmer_email = ? AND client_id = ?');
        $stockCheck->execute([$owner,$stockClientId]);
        if ($stockCheck->fetchColumn() === false) journal_response(['error'=>'The selected supply is not in your account.'], 422);
        $delta = $record['quantityDelta'] ?? null;
        if (!is_numeric($delta) || (float)$delta == 0 || abs((float)$delta) > 1000000000) journal_response(['error'=>'Enter a valid stock change.'], 422);
        if ((float)$delta < 0) {
            $balanceQuery = $db->prepare('SELECT quantity + COALESCE((SELECT SUM(quantity_delta) FROM farm_stock_movements WHERE farmer_email = ? AND stock_client_id = ?), 0) FROM farm_inventory WHERE farmer_email = ? AND client_id = ?');
            $balanceQuery->execute([$owner,$stockClientId,$owner,$stockClientId]);
            if ((float)$balanceQuery->fetchColumn() + (float)$delta < -0.0005) journal_response(['error'=>'There is not enough stock for that amount.'], 422);
        }
        $entryDate = journal_date($record, 'entryDate');
        $sql = 'INSERT INTO farm_stock_movements (farmer_email,client_id,stock_client_id,linked_entry_client_id,quantity_delta,entry_date) VALUES (?,?,?,?,?,?) ON CONFLICT (farmer_email,client_id) DO UPDATE SET stock_client_id=EXCLUDED.stock_client_id,linked_entry_client_id=EXCLUDED.linked_entry_client_id,quantity_delta=EXCLUDED.quantity_delta,entry_date=EXCLUDED.entry_date,updated_at=NOW()';
        if ($driver !== 'pgsql') $sql = 'INSERT INTO farm_stock_movements (farmer_email,client_id,stock_client_id,linked_entry_client_id,quantity_delta,entry_date) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE stock_client_id=VALUES(stock_client_id),linked_entry_client_id=VALUES(linked_entry_client_id),quantity_delta=VALUES(quantity_delta),entry_date=VALUES(entry_date),updated_at=CURRENT_TIMESTAMP(6)';
        $statement = $db->prepare($sql); $statement->execute([$owner,$clientId,$stockClientId,$linkedEntryClientId,(float)$delta,$entryDate]);
    } elseif ($entity === 'yield') {
        $fieldName = journal_string($record, 'fieldName', 120, true);
        $crop = journal_string($record, 'crop', 120, true);
        $harvestDate = journal_date($record, 'harvestDate');
        $quantity = journal_decimal($record, 'quantity');
        if ($quantity <= 0) journal_response(['error'=>'Harvest quantity must be greater than zero.'], 422);
        $unit = journal_string($record, 'unit', 30, true);
        $grade = journal_string($record, 'grade', 60);
        $saleAmount = journal_decimal($record, 'saleAmount');
        $note = journal_string($record, 'note', 4000);
        $sql = 'INSERT INTO field_harvests (farmer_email,client_id,field_name,crop,harvest_date,quantity,unit,grade,sale_amount,note) VALUES (?,?,?,?,?,?,?,?,?,?) ON CONFLICT (farmer_email,client_id) DO UPDATE SET field_name=EXCLUDED.field_name,crop=EXCLUDED.crop,harvest_date=EXCLUDED.harvest_date,quantity=EXCLUDED.quantity,unit=EXCLUDED.unit,grade=EXCLUDED.grade,sale_amount=EXCLUDED.sale_amount,note=EXCLUDED.note,updated_at=NOW()';
        if ($driver !== 'pgsql') $sql = 'INSERT INTO field_harvests (farmer_email,client_id,field_name,crop,harvest_date,quantity,unit,grade,sale_amount,note) VALUES (?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE field_name=VALUES(field_name),crop=VALUES(crop),harvest_date=VALUES(harvest_date),quantity=VALUES(quantity),unit=VALUES(unit),grade=VALUES(grade),sale_amount=VALUES(sale_amount),note=VALUES(note),updated_at=CURRENT_TIMESTAMP(6)';
        $statement = $db->prepare($sql); $statement->execute([$owner,$clientId,$fieldName,$crop,$harvestDate,$quantity,$unit,$grade,$saleAmount,$note]);
    } else {
        journal_response(['error'=>'Invalid record type.'], 422);
    }

    $idStatement = $db->prepare("SELECT id, updated_at FROM {$table} WHERE farmer_email = ? AND client_id = ?");
    $idStatement->execute([$owner,$clientId]);
    $saved = $idStatement->fetch();
    $db->commit();
    journal_response(['ok'=>true,'id'=>(int)$saved['id'],'clientId'=>$clientId,'updatedAt'=>(string)$saved['updated_at']]);
} catch (Throwable $exception) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('Farm journal API error: ' . $exception->getMessage());
    journal_response(['error'=>'Could not save the farm journal entry. Please retry when online.'], 500);
}
