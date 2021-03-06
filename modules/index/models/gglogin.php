<?php
/**
 * @filesource modules/index/models/gglogin.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Index\Gglogin;

use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * Google Login.
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * รับข้อมูลที่ส่งมาจากการเข้าระบบด้วยบัญชี FB.
     *
     * @param Request $request
     */
    public function chklogin(Request $request)
    {
        // session, token
        if ($request->initSession() && $request->isSafe()) {
            // สุ่มรหัสผ่านใหม่
            $password = uniqid();
            // ข้อมูลที่ส่งมา
            $save = array(
                'name' => $request->post('name')->topic(),
                'email' => $request->post('email')->url(),
            );
            $id = $request->post('id')->number();
            $save['displayname'] = $save['name'];
            // db
            $db = $this->db();
            // table
            $user_table = $this->getTableName('user');
            // ตรวจสอบสมาชิกกับ db
            $search = $db->createQuery()
                ->from('user')
                ->where(array('email', $save['email']), array('displayname', $save['displayname']), 'OR')
                ->toArray()
                ->first();
            if ($search === false) {
                // ยังไม่เคยลงทะเบียน, ลงทะเบียนใหม่
                if (self::$cfg->demo_mode) {
                    // โหมดตัวอย่าง สามารถเข้าระบบหลังบ้านได้
                    $save['active'] = 1;
                    $save['permission'] = 'can_config';
                } else {
                    $save['active'] = 0;
                    $save['permission'] = '';
                }
                $save['status'] = self::$cfg->new_register_status;
                $save['id'] = $db->getNextId($this->getTableName('user'));
                $save['social'] = 2;
                $save['visited'] = 1;
                $save['ip'] = $request->getClientIp();
                $save['salt'] = uniqid();
                $save['password'] = sha1($password.$save['salt']);
                $save['lastvisited'] = time();
                $save['create_date'] = $save['lastvisited'];
                $save['icon'] = $save['id'].'.jpg';
                $save['country'] = 'TH';
                $db->insert($user_table, $save);
            } elseif ($search['social'] == 2) {
                // google เคยเยี่ยมชมแล้ว อัปเดทการเยี่ยมชม
                $save = $search;
                ++$save['visited'];
                $save['lastvisited'] = time();
                $save['ip'] = $request->getClientIp();
                $save['salt'] = uniqid();
                $save['token'] = sha1($password.$save['salt']);
                // อัปเดท
                $db->update($user_table, $search['id'], $save);
            } else {
                // ไม่สามารถ login ได้ เนื่องจากมี email อยู่ก่อนแล้ว
                $save = false;
                $ret['alert'] = Language::replace('This :name already exist', array(':name' => Language::get('User')));
                $ret['isMember'] = 0;
            }
            if (is_array($save) && !empty($id)) {
                // อัปเดท icon สมาชิก
                $data = @file_get_contents($request->post('image')->url());
                if ($data) {
                    $f = @fopen(ROOT_PATH.self::$cfg->usericon_folder.$save['icon'], 'wb');
                    if ($f) {
                        fwrite($f, $data);
                        fclose($f);
                    }
                }
                // login
                $save['permission'] = empty($save['permission']) ? array() : explode(',', trim($save['permission'], " \t\n\r\0\x0B,"));
                $_SESSION['login'] = $save;
                // คืนค่า
                $ret['action'] = $request->post('login_action')->toString();
                $ret['alert'] = Language::replace('Welcome %s, login complete', array('%s' => $save['name']));
                $ret['content'] = rawurlencode(createClass('Index\Login\View')->member($save));
                // เคลียร์
                $request->removeToken();
            }
            // คืนค่าเป็น json
            echo json_encode($ret);
        }
    }
}
