<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/AdminController.php';

/** Participant platform credentials (never administrator/department roles). */
class ParticipantUserController {
    private function authorize($admin){
        if(!$admin || ($admin['admin_role']??'')!=='super_admin') throw new Exception(t('not_allowed'));
    }
    private function audit($adminId,$event,$userId,$details){
        $entity='user'; $q=db()->prepare('INSERT INTO administrative_audit_log(actor_admin_id,event_type,entity_type,entity_id,details,created_at) VALUES(?,?,?,?,?,NOW())');
        $q->bind_param('issis',$adminId,$event,$entity,$userId,$details);$q->execute();
    }
    private function normalizedUsername($value){
        $username=strtolower(trim((string)$value));
        if(!valid_platform_username($username)) throw new Exception('Username: ٣ تا ٣٠ پیت؛ دەستپێک بە پیتی ئینگلیزی، پاشان ژمارە یان . _ -');
        return $username;
    }
    public function accounts($admin,$search=''){
        $this->authorize($admin);
        $search=mb_substr(trim((string)$search),0,100);
        if($search==='') return db()->query("SELECT u.user_id,u.unique_id,u.username,u.email,u.role,u.created_at,u.fname,u.middle_name,u.lname,r.issued_id,r.status AS registry_status FROM users u LEFT JOIN institution_registry r ON r.id=u.registry_id ORDER BY u.user_id DESC LIMIT 100")->fetch_all(MYSQLI_ASSOC);
        $term='%'.$search.'%';
        $q=db()->prepare("SELECT u.user_id,u.unique_id,u.username,u.email,u.role,u.created_at,u.fname,u.middle_name,u.lname,r.issued_id,r.status AS registry_status FROM users u LEFT JOIN institution_registry r ON r.id=u.registry_id WHERE u.username LIKE ? OR u.email LIKE ? OR r.issued_id LIKE ? OR u.fname LIKE ? OR u.lname LIKE ? ORDER BY u.user_id DESC LIMIT 100");
        $q->bind_param('sssss',$term,$term,$term,$term,$term);$q->execute();return $q->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    public function available($admin,$search=''){
        $this->authorize($admin);
        $search=mb_substr(trim((string)$search),0,100);
        if($search==='') return db()->query("SELECT id,issued_id,first_name,middle_name,last_name,person_type FROM institution_registry WHERE status='available' ORDER BY id DESC LIMIT 100")->fetch_all(MYSQLI_ASSOC);
        $term='%'.$search.'%';
        $q=db()->prepare("SELECT id,issued_id,first_name,middle_name,last_name,person_type FROM institution_registry WHERE status='available' AND (issued_id LIKE ? OR first_name LIKE ? OR middle_name LIKE ? OR last_name LIKE ?) ORDER BY id DESC LIMIT 100");
        $q->bind_param('ssss',$term,$term,$term,$term);$q->execute();return $q->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    public function create($admin,$issuedId,$username,$password,$email){
        $this->authorize($admin);
        $username=$this->normalizedUsername($username);
        $issuedId=strtoupper(trim((string)$issuedId));
        $email=strtolower(trim((string)$email));
        if(strlen((string)$password)<8) throw new Exception('پاسۆرد دەبێت لانیکەم ٨ نووسە بێت.');
        if($email!=='' && (!filter_var($email,FILTER_VALIDATE_EMAIL)||mb_strlen($email)>190)) throw new Exception(t('invalid_email'));
        if($issuedId==='') throw new Exception(t('required_fields'));
        $conn=db();$conn->begin_transaction();
        try{
            $q=$conn->prepare("SELECT * FROM institution_registry WHERE issued_id=? LIMIT 1 FOR UPDATE");
            $q->bind_param('s',$issuedId);$q->execute();$person=$q->get_result()->fetch_assoc();
            if(!$person || $person['status']!=='available') throw new Exception('ئەم ID ـیە پەسەند نەکراوە، ڕاگیراوە یان پێشتر هەژماری هەیە.');
            // Staff who later become department employees also need a genuine email address.
            if($person['person_type']==='staff' && $email==='') throw new Exception('بۆ هەژماری فەرمانبەر پێویستە ئیمەیڵێکی ڕاستەقینە دیاری بکرێت.');
            $registryId=(int)$person['id'];
            $q=$conn->prepare('SELECT user_id FROM users WHERE registry_id=? LIMIT 1');$q->bind_param('i',$registryId);$q->execute();
            if($q->get_result()->fetch_assoc()) throw new Exception('ئەم کەسە پێشتر هەژماری هەیە.');
            $q=$conn->prepare('SELECT user_id FROM users WHERE username=? LIMIT 1');$q->bind_param('s',$username);$q->execute();
            if($q->get_result()->fetch_assoc()) throw new Exception('ئەم ناوی بەکارهێنەرە پێشتر بەکارهاتووە.');
            $mail=$email!==''?$email:null;
            if($mail!==null){$q=$conn->prepare('SELECT user_id FROM users WHERE email=? LIMIT 1');$q->bind_param('s',$mail);$q->execute();if($q->get_result()->fetch_assoc())throw new Exception(t('email_taken'));}
            do{$uniqueId=random_int(100000000,999999999);}while(user_unique_id_taken($uniqueId));
            $fname=$person['first_name'];$middle=$person['middle_name'];$lname=$person['last_name'];$role=$person['person_type'];
            $hash=password_hash($password,PASSWORD_DEFAULT);
            $q=$conn->prepare("INSERT INTO users(unique_id,fname,middle_name,lname,username,email,password,img,status,last_seen_at,language,role,bio,registry_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,'assets/img/default-avatar.svg','Offline now',NULL,'ku',?,'',?,NOW(),NOW())");
            $q->bind_param('isssssssi',$uniqueId,$fname,$middle,$lname,$username,$mail,$hash,$role,$registryId);$q->execute();
            $userId=(int)$q->insert_id;
            $q=$conn->prepare("UPDATE institution_registry SET status='registered',updated_at=NOW() WHERE id=? AND status='available'");
            $q->bind_param('i',$registryId);$q->execute();if($q->affected_rows!==1)throw new Exception(t('registration_id_unavailable'));
            $this->audit((int)$admin['id'],'participant_user_created',$userId,$issuedId.' | '.$username);
            $conn->commit();
        }catch(Throwable $e){$conn->rollback();throw $e;}
    }
    public function assignUsername($admin,$userId,$username){
        $this->authorize($admin);$username=$this->normalizedUsername($username);$userId=(int)$userId;
        $conn=db();$conn->begin_transaction();
        try{
            $q=$conn->prepare('SELECT user_id,username FROM users WHERE user_id=? LIMIT 1 FOR UPDATE');$q->bind_param('i',$userId);$q->execute();$u=$q->get_result()->fetch_assoc();
            if(!$u || !empty($u['username']))throw new Exception('هەژمارەکە نەدۆزرایەوە یان پێشتر Username ـی هەیە.');
            $q=$conn->prepare('UPDATE users SET username=?,updated_at=NOW() WHERE user_id=? AND username IS NULL');
            $q->bind_param('si',$username,$userId);$q->execute();if($q->affected_rows!==1)throw new Exception(t('invalid_action'));
            $this->audit((int)$admin['id'],'participant_username_assigned',$userId,$username);
            $conn->commit();
        }catch(Throwable $e){$conn->rollback();throw $e;}
    }
}
