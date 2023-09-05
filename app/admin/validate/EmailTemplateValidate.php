<?php


namespace app\admin\validate;

class EmailTemplateValidate extends \think\Validate
{
    protected $rule = ["type" => "require|in:general,product,invoice,support,notification,admin", "name" => "require|max:100", "fromname" => "max:100", "fromemail" => "email", "copyto" => "max:1000", "blind_copy_to" => "max:1000", "plaintext" => "in:0,1", "disabled" => "in:0,1", "subject" => "require|max:100", "message" => "require", "file" => "fileExt:png,jpg,jpeg,doc,gif,docx,xls,xlsx,ppt,pptx,pps,pdf,key,numbers,pages,xml,odt,swf,gz,tgz,bz,bz2,tbz,zip,rar,tar,txt,php,html,htm,js,css,vcf,rtf,rtfd,py,java,rb,sh,pl,sql|fileMime:image/jpeg,image/png,image/gif,image/bmp,application/vnd.ms-word,application/vnd.ms-excel,application/vnd.ms-powerpoint,application/pdf,application/xml,application/vnd.oasis.opendocument.text,application/x-shockwave-flash,application/x-gzip,application/x-bzip2,application/zip,application/x-rar,text/plain,text/x-php,text/html,text/javascript,text/css,text/rtf,text/rtfd,text/x-python,text/x-java-source,text/x-ruby,text/x-shellscript,text/x-perl,text/x-sql,application/octet-stream,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/msword|fileSize:67108864"];
    protected $message = ["type.require" => "{%EMAIL_TEMPLATE_TYPE_REQUIRE}", "type.in" => "{%EMAIL_TEMPLATE_TYPE_IN}", "name.require" => "{%EMAIL_TEMPLATE_NAME_REQUIRE}", "name.max" => "{%EMAIL_TEMPLATE_NAME_MAX}", "fromname.max" => "{%EMAIL_TEMPLATE_FROMNAME_MAX}", "fromemail.email" => "{%EMAIL_TEMPLATE_FROMEMAIL_EMAIL}", "copyto.max" => "{%EMAIL_TEMPLATE_COPY_TO_MAX}", "blind_copy_to.max" => "{%EMAIL_TEMPLATE_BLIND_COPY_TO_MAX}", "plaintext.in" => "{%EMAIL_TEMPLATE_PLAINTEXT_IN}", "disabled.in" => "{%EMAIL_TEMPLATE_DISABLED_IN}", "subject.require" => "{%EMAIL_TEMPLATE_SUBJECT_REQUIRE}", "subject.max" => "{%EMAIL_TEMPLATE_SUBJECT_MAX}", "message.require" => "{%EMAIL_TEMPLATE_MESSAGE_REQUIRE}", "file.fileMime" => "{%EMAIL_TEMPLATE_FILE_MIME_ERROR}", "file.fileSize" => "{%EMAIL_TEMPLATE_FILE_MAX}"];
    protected $scene = ["email" => ["name", "type"], "edit_email" => ["fromname", "fromemail", "copyto", "blind_copy_to", "plaintext", "disabled", "subject"], "upload" => ["file"]];
}

?>