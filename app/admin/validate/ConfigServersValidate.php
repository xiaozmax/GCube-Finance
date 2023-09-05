<?php


namespace app\admin\validate;

class ConfigServersValidate extends \think\Validate
{
    protected $rule = ["name" => "require|max:50", "ip_address" => "require|max:50", "hostname" => "max:50", "status_address" => "max:100", "assigned_ips" => "max:500", "username" => "max:256", "password" => "max:256", "disabled" => "in:0,1", "accesshash" => "max:5000", "noc" => "max:1000", "secure" => "in:0,1", "port" => "number|max:50", "gid" => "require", "file" => "require|image|fileExt:png,jpg,jpeg,gif|fileMime:image/jpeg,image/png,image/gif|fileSize:10485760", "group_name" => "require|max:256", "type" => "require"];
    protected $message = ["name.require" => "{%SERVER_NAME_REQUIRE}", "name.max" => "{%SERVER_NAME_MAX}", "ip_address.require" => "{%SERVER_IP_ADDRESS_REQUIRE}", "ip_address.max" => "{%SERVER_IP_ADDRESS_MAX}", "hostname.max" => "{%SERVER_HOSTNAME_MAX}", "status_address.max" => "{%SERVER_STATUS_ADDRESS_MAX}", "assigned_ips.max" => "{%SERVER_ASSIGNED_IPS_MAX}", "username.max" => "{%SERVER_USERNAME_MAX}", "password.max" => "{%SERVER_PASSWORD_MAX}", "accesshash.max" => "{%SERVER_ACCESSHASH_MAX}", "port.max" => "{%SERVER_PORT_MAX}", "gid.require" => "{%SERVER_GROUP_REQUIRE}", "group_name.require" => "{%SERVER_GROUPS_NAME_REQUIRE}", "group_name.max" => "{%SERVER_GROUPS_NAME_MAX}", "type.require" => "{%SERVER_GROUPS_MODULE_REQUIRE}", "type.max" => "{%SERVER_GROUPS_MODULE_MAX}", "file.require" => "{%IMAGE_REQUIRE}", "file.image" => "{%IMAGE}", "file.fileExt" => "{%IMAGE_TYPE}", "file.fileMime" => "{%IMAGE_IMME}", "file.fileSize" => "{%IMAGE_MAX_10}"];
    protected $scene = ["create_servers" => ["name", "ip_address", "hostname", "status_address", "assigned_ips", "username", "password", "accesshash", "port", "secure", "noc", "disabled", "type"], "create_group" => ["group_name", "type"], "upload" => ["file"]];
}

?>