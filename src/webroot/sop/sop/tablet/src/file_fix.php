<?php
include_once(__DIR__."/login_check.php");
include_once(__DIR__."/config.php");
include_once(__DIR__."/../../src/db_common.php");

\Sop\Database::setupRedBean();

/**
 * ファイル 作業完了
 */
$db = createDBConnection();

// ---------------------------
// parameters 取得
// ---------------------------
$grp_id  = \Sop\Session::getSiteData('grp_id');
$user_id = \Sop\Session::getSiteData('user_id');

$user_id_1 = (array_key_exists('user_id_1', $_REQUEST) ? $_REQUEST['user_id_1'] : '');
$password_1 = (array_key_exists('password_1', $_REQUEST) ? md5($_REQUEST['password_1']) : '');

$pj_id = (array_key_exists('pj_id', $_REQUEST)) ? $_REQUEST['pj_id'] : '';
$sop_id = (array_key_exists('sop_id', $_REQUEST)) ? $_REQUEST['sop_id'] : '';
$tpl_id = (array_key_exists('tpl_id', $_REQUEST)) ? $_REQUEST['tpl_id'] : '';
$schema_id = (array_key_exists('schema_id', $_REQUEST)) ? $_REQUEST['schema_id'] : '';
$file_id = (array_key_exists('file_id', $_REQUEST)) ? $_REQUEST['file_id'] : '';
$smpl_given_no = (array_key_exists('smpl_given_no', $_REQUEST)) ? $_REQUEST['smpl_given_no'] : '';

$date = date("Y-m-d H:i:s");

// ---------------------------
// DB 更新
// ---------------------------
$db->beginTransaction();

// 入力チェックが必要なテンプレートかどうか確認

$sql = getSQLBaseForSopList();
$sql .= " AND sop.sop_id = :sop_id";
$sop = R::getRow($sql, array('sop_id' => $sop_id));
if (! $sop) {
    \Sop\Log::warning(__FILE__, __LINE__, 'User tries to fix non-existent sop.');
    $msg001 = "The update failed.: no sop"; // 更新に失敗しました: no sop
    \Sop\Api::exitWithError(array($msg001));
}

// チェックが必要な場合、「チェック待ち」ステータスにする。
if ($sop['checker_required_flag']) {
    $rslt = updFileProvisionalFix($db, $file_id, $date);
    if(!$rslt)
    {
        \Sop\Log::error(__FILE__, __LINE__, 'Failed to update file.');
        $msg002 = "The update failed: file"; // 更新に失敗しました: file
        \Sop\Api::exitWithError(array($msg002));
    }
} else {
    $rslt = updFileFix($db, $file_id, $date, $user_id);
    if(!$rslt)
    {
        \Sop\Log::error(__FILE__, __LINE__, 'Failed to update file.');
        $msg003 = "The update failed: file"; // 更新に失敗しました: file
        \Sop\Api::exitWithError(array($msg003));
    }
}

// TBL: history file_fix
$history_id = -1;
$rslt = addHistory($db, $history_id, $pj_id, $sop_id, $tpl_id, $schema_id, $file_id, $smpl_given_no, $HISTORY_ACTION_FILE_PROVISIONAL_FIX, $date, $user_id, null, null);
if(!$rslt)
{
    \Sop\Log::error(__FILE__, __LINE__, 'Failed to add history.');
    $msg004 = "The update failed.: history file_provisional_fix"; // 更新に失敗しました: history file_provisional_fix
    \Sop\Api::exitWithError(array($msg004));
}
$history_id++;

// TBL: history file_fix_aprv
$rslt = addHistory($db, $history_id, $pj_id, $sop_id, $tpl_id, $schema_id, $file_id, $smpl_given_no, $HISTORY_ACTION_FILE_PROVISIONAL_FIX_APRV, $date, null, null, null);
if(!$rslt)
{
    \Sop\Log::error(__FILE__, __LINE__, 'Failed to add history.');
    $msg005 = "The update failed.: history file_provisional_fix_aprv"; // 更新に失敗しました: history file_provisional_fix_aprv
    \Sop\Api::exitWithError(array($msg005));
}

// ---------------------------
// 出力
// ---------------------------
$db->commit();
$db = null;

header("Content-type:application/json; charset=utf-8");
if ($sop['checker_required_flag']) {
    $msg006 = "The state turned to the complete operation that wait for the confirmation."; // 作業完了（チェック待ち）にしました
    echo json_encode(array('success'=>true, 'msg'=> \Sop\Api::htmlEncodeLines(array($msg006))));
} else {
    $msg007 = "The operation completed."; // 作業完了しました
    echo json_encode(array('success'=>true, 'msg'=> \Sop\Api::htmlEncodeLines(array($msg007))));
}
exit;
