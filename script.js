
document.addEventListener("DOMContentLoaded", function () {

    // Set the minimum appointment date to today
    const dateInput = document.getElementById("date");

    if (dateInput) {
        const today = new Date();
        const localDate = new Date(
            today.getTime() - today.getTimezoneOffset() * 60000
        ).toISOString().split("T")[0];

        dateInput.min = localDate;
    }

    // Demo login form
    const loginForm = document.getElementById("loginForm");

    if (loginForm) {
        loginForm.addEventListener("submit", function (event) {
            event.preventDefault();

            document.getElementById("message").textContent =
                "Frontend demo only. PHP login is not connected yet.";
        });
    }

    // Demo appointment form
    const appointmentForm =
        document.getElementById("appointmentForm");

    if (appointmentForm) {
        appointmentForm.addEventListener("submit", function (event) {
            event.preventDefault();

            document.getElementById("appointmentMessage").textContent =
                "Form validated. Connect PHP and MariaDB to save the appointment.";

            document.getElementById("appointmentMessage").className =
                "mt-3 text-center text-success";
        });
    }

    // Demo queue lookup
    const queueForm = document.getElementById("queueForm");

    if (queueForm) {
        queueForm.addEventListener("submit", function (event) {
            event.preventDefault();

            const token = document.getElementById("token").value;
            const result = document.getElementById("queueResult");

            result.textContent =
                "Token entered: " + token +
                ". Actual queue status will appear after database connection.";

            result.classList.remove("d-none");
        });
    }

});
