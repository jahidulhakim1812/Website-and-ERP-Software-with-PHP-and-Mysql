<?php
$to = "mdjhk19@gmail.com";
$subject = "Test Email";
$message = "This is a test email from your server.";
$headers = "From: contact@alihairwigs.com";

if(mail($to, $subject, $message, $headers)) {
    echo "Email sent successfully!";
} else {
    echo "Email failed to send.";
    print_r(error_get_last());
}
?>