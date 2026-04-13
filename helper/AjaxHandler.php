<?php
class AjaxHandler {
    
    public static function respondJSON($success, $data = null, $message = null, $httpCode = 200) {
        header('Content-Type: application/json');
        http_response_code($httpCode);
        
        $response = [
            'success' => $success,
            'data' => $data,
            'message' => $message
        ];
        
        echo json_encode($response);
        exit;
    }
    
    public static function isAjaxRequest() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    public static function validatePostData($required_fields) {
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                return false;
            }
        }
        return true;
    }
    
    public static function validateGetData($required_fields) {
        foreach ($required_fields as $field) {
            if (empty($_GET[$field])) {
                return false;
            }
        }
        return true;
    }
}
?>