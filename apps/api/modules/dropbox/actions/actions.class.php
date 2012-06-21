<?php
class dropboxActions extends opJsonApiActions
{

  public function getDropbox(){
    $oauth = new Dropbox_OAuth_PEAR(sfConfig::get('app_consumer'),sfConfig::get('app_consumer_secret'));
    $oauth->setToken(sfConfig::get('app_token'),sfConfig::get('app_token_secret'));

    $dropbox = new Dropbox_API($oauth);
    return $dropbox;
  }

  public function executeDelete(sfWebRequest $request)
  {
    $path = $request->getParameter("path");
    $dropbox = $this->getDropbox();
    $delete_ok = false
    if(preg_match('/^PNE\/m(\d+)/',$path,$match)){
      $path_member_id = $match[1];
      //member mode
      $member_id = $this->getUser()->getMember()->getId();
      if($path_member_id != $member_id){
        return $this->renderJSON(array('status' => 'error','message' => 'you can delete only your own directary. ' . $path));
      }
      $delete_ok = true;
    }
    else if(preg_match('/^PNE\/c(\d+)/',$path,$match)){
      $path_community_id = $match[1];
      $community = Doctrine::getTable("Community")->find($community_id);
      if(!$community->isAdmin($this->getUser()->getMember()->getId())){
        return $this->renderJSON(array('status' => 'error','message' => 'only community_admin can delete community directary. ' . $path));
      }
      $delete_ok = true;
    }
    if($is_ok){
      $response = $dropbox->delete($path);
      return $this->renderJSON(array('status' => 'success','data' => $response));
    }
  }

  public function executeList(sfWebRequest $request)
  {
    $dropbox = $this->getDropbox();
    $response = $dropbox->getMetaData('/PNE/');
    return $this->renderJSON(array('status' => 'success','data' => $response));
  }

  public function executeFiles(sfWebRequest $request)
  {
    $path = $request->getParameter("path");
    if(strpos($path, "/PNE/") !== 0){
      return $this->renderJSON(array('status' => 'error','message' => 'only accept /PNE/ directory,' . $path));
    }
    $dropbox = $this->getDropbox();
    $data = $dropbox->getFile($path);

     
    if(!$data){
      return $this->renderJSON(array('status' => 'error','message' => "Dropbox file download error"));
    }

    $filename = substr($path,5);

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $type = $finfo->buffer($data);
    $this->getResponse()->setHttpHeader('Content-Type',$type);
    //if(strpos($type,'application') !== FALSE || $type == "text/x-php"){
      $this->getResponse()->setHttpHeader('Content-Disposition','attachment; filename="'.$filename.'"');
    //}
    return $this->renderText($data);
  }
  public function executeShare(sfWebRequest $request)
  {
   
    $dropbox = $this->getDropbox();
    $path = $request->getParameter("path");
    if(strpos($path, "/PNE") !== 0){
      return $this->renderJSON(array('status' => 'error','message' => 'only accept /PNE/ directory,' . $path));
    }
    $response = $dropbox->share($path);
    return $this->renderJSON(array('status' => 'success','data' => $response));
  }
  public function executeUpload(sfWebRequest $request)
  {
    $filename = basename($_FILES['upfile']['name']);
    if(!$filename){
      return $this->renderJSON(array('status' => 'error' ,'path' => $response, 'message' => "null file"));
    }

    $community_id = (int)$request->getParameter("community_id");
    if((int)$community_id >= 1){
      $community = Doctrine::getTable("Community")->find($community_id);
      if(!$community->isPrivilegeBelong($this->getUser()->getMember()->getId())){
        return $this->renderJSON(array('status' => 'error' ,'message' => "you are not this community member."));
      }
      
      $dirname = '/PNE/c'. $community_id;
    }else{

      $dirname = '/PNE/c'. $community_id;
      $filepath = '/PNE/m'. $this->getUser()->getMember()->getId() 
    }
   
    //validate $filepath
    if(!preg_match('/PNE\/[cm]\d+\/.*$')){
      return $this->renderJSON(array('status' => 'error' ,'message' => "file path error."));
    }
 
    $dropbox = $this->getDropbox();
    try{
      $response = $dropbox->createFolder($dirname);
    }catch(Exception $e){
      error_log($e->toString(), 3, "/tmp/loglog"); 
    }
    $response = $dropbox->putFile($dirname .'/'. $_FILES['upfile']['tmp_name']);
    if($response === true){
      //$response = $dropbox->share('/PNE/'.$filename);
      return $this->renderJSON(array('status' => 'success' , 'message' => "file up success"));
    }else{
      return $this->renderJSON(array('status' => 'error','message' => "Dropbox file upload error"));
    }
  }
}
