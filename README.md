Config variables need to be set in config.php. Examples below:
$CFG->assignmentUploadDir = 'C:\\inetpub\\ftproot\assignmenttoupload\\';

THINGS TO WATCH FOR:
uploadFileFromServer.php needs to upload to the database table mdl_repository->upload column. 
This is currently index 5 but may change with future updates. Change this by finding repoId=5 string.

