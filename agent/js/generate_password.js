function generatePassword(targetId = 'password') {
    // Send a GET request to ajax.php as ajax.php?get_readable_pass=true
    jQuery.get(
        "ajax.php", {
            get_readable_pass: 'true'
        },
        function(data) {
            //If we get a response from post.php, parse it as JSON
            const password = JSON.parse(data);

            const el = typeof targetId === 'string' ? document.getElementById(targetId) : null;
            if (el) {
                el.value = password;
            } else if (document.getElementById("password")) {
                document.getElementById("password").value = password;
            }
        }
    );
}
