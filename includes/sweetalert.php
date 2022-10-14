<?php

if(isset($_SESSION['errorMessage'])) { ?>
    <script>
        Swal.fire({
            icon: "error",
            title: "<?php echo $_SESSION['titleMessage'];?>",
            html: "<?php echo $_SESSION['errorMessage'];?>"
        });
    </script>
<?php } 

if(isset($_SESSION['successMessage'])) { ?>
    <script>
        Swal.fire({
            icon: "success",
            title: "<?php echo $_SESSION['titleMessage'];?>",
            html: "<?php echo $_SESSION['successMessage'];?>"
        });
    </script>
<?php }

if(isset($_SESSION['infoMessage'])) { ?>
    <script>
        Swal.fire({
            icon: "info",
            title: "<?php echo $_SESSION['titleMessage'];?>",
            html: "<?php echo $_SESSION['infoMessage'];?>"
        });
    </script>
<?php } 

unset($_SESSION['titleMessage']); unset($_SESSION['errorMessage']); unset($_SESSION['successMessage']); unset($_SESSION['infoMessage']);

?>