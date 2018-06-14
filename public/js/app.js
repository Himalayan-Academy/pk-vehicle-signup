function displayNewReservationModal(ev) {
    var carName = this.getAttribute("data-car-name");
    var carId = this.getAttribute("data-car-id");
    var currentDate = this.getAttribute("data-day");
    var who = this.getAttribute("data-who");
    var comment = this.getAttribute("data-comment");
    var reservationId = this.getAttribute("data-reservationId");
    var hour_start = this.getAttribute("reservation-hour-start");
    var hour_end = this.getAttribute("reservation-hour-end");


    var modal = document.getElementById("signup-modal");

    // Making sure that we "deselect" all cars before showing the modal...
    var allCarOptions = document.querySelectorAll("#reservation-car option");
    for(var i = 0; i<allCarOptions.length; i++) {
        allCarOptions[i].removeAttribute("selected");
    }

    // If we pass an "car_id" to the modal, then it should be pre-selected...
    if (carId) {
        document.getElementById("car-" + carId).setAttribute("selected", true);
    }

    if (hour_start) {
        document.getElementById("reservation-hour-start").value = hour_start;
    }

    if (hour_end) {
        document.getElementById("reservation-hour-end").value = hour_end;
    }

    if (currentDate) {
        document.getElementById("reservation-date").value = currentDate;
    }

    if (comment) {
        document.getElementById("reservation-comments").value = comment;
    }

    if (who) {
        document.getElementById("reservation-who").value = who;
    }



    modal.classList.add("is-active");
    console.log("[MODAL] new sign up request",carName, currentDate);
}

function displayEditReservationModal(ev) {
    var carName = this.getAttribute("data-car-name");
    var carId = this.getAttribute("data-car-id");
    var day = this.getAttribute("data-day");
    var who = this.getAttribute("data-who");
    var comment = this.getAttribute("data-comment");
    var reservationId = this.getAttribute("data-reservation-id");
    var hour_start = this.getAttribute("data-start").substr(11); // needed because the data in the attribute contains the date as well
    var hour_end = this.getAttribute("data-end").substr(11); // needed because the data in the attribute contains the date as well


    var modal = document.getElementById("edit-modal");

    // Making sure that we "deselect" all cars before showing the modal...
    var allCarOptions = document.querySelectorAll("#edit-reservation-car option");
    for(var i = 0; i<allCarOptions.length; i++) {
        allCarOptions[i].removeAttribute("selected");
    }

    if (reservationId) {
        document.getElementById("edit-reservation-id").value = reservationId;
    }

    // If we pass an "car_id" to the modal, then it should be pre-selected...
    if (carId) {
        document.getElementById("edit-car-" + carId).setAttribute("selected", true);
    }

    if (hour_start) {
        document.getElementById("edit-reservation-hour-start").value = hour_start;
    }

    if (hour_end) {
        document.getElementById("edit-reservation-hour-end").value = hour_end;
    }

    if (day) {
        document.getElementById("edit-reservation-date").value = day;
    }

    if (comment) {
        document.getElementById("edit-reservation-comments").value = comment;
    }

    if (who) {
        document.getElementById("edit-reservation-who").value = who;
    }



    modal.classList.add("is-active");
    console.log("[MODAL] edit request", reservationId, carName, day);
}


/*
 * Code to save/edit reservation to the database.
 */

function saveReservation() {
    var reservation_date = document.getElementById("reservation-date").value
    var hour_start = document.getElementById("reservation-hour-start").value
    var hour_end = document.getElementById("reservation-hour-end").value
    var who = document.getElementById("reservation-who").value
    var comments = document.getElementById("reservation-comments").value
    var id = document.getElementById("reservation-car").value

    var obj = {
        id: id,
        data: {
            date: reservation_date,
            start: hour_start,
            end: hour_end,
            who: who,
            comment: comments
        }
    }

    document.querySelector("#reservation-modal-footer > span:nth-child(1) > i:nth-child(1)").classList.remove("is-hidden");

    $.post("/signup/new", obj, function(data) {
        console.log("[AJAX] /signup/new", data);

        if (data.status === "ok") {
            window.location.reload();
        } else {
            alert("Error making reservation: " + data.msg);
            window.location.reload();
        }
    });
}

function updateReservation() {
    var reservation_date = document.getElementById("edit-reservation-date").value
    var hour_start = document.getElementById("edit-reservation-hour-start").value
    var hour_end = document.getElementById("edit-reservation-hour-end").value
    var who = document.getElementById("edit-reservation-who").value
    var comments = document.getElementById("edit-reservation-comments").value
    var id = document.getElementById("edit-reservation-car").value
    var reservation_id = document.getElementById("edit-reservation-id").value

    var obj = {
        reservation_id: reservation_id,
        data: {
            car_id: id,
            date: reservation_date,
            start: hour_start,
            end: hour_end,
            who: who,
            comment: comments
        }
    }

    document.querySelector("#edit-reservation-modal-footer > span:nth-child(1) > i:nth-child(1)").classList.remove("is-hidden");

    $.post("/signup/update", obj, function(data) {
        console.log("[AJAX] /signup/update", data);

        if (data.status === "ok") {
            window.location.reload();
        } else {
            alert("Error updating reservation: " + data.msg);
            console.error("[ERROR] /signup/update", data);
            window.location.reload();
        }
    });


}

function removeReservation() {

    var reservation_id = document.getElementById("edit-reservation-id").value

    var obj = {
        reservation_id: reservation_id
    }

    document.querySelector("#edit-reservation-modal-footer > span:nth-child(1) > i:nth-child(1)").classList.remove("is-hidden");

    $.post("/signup/remove", obj, function(data) {
        console.log("[AJAX] /signup/remove", data);

        if (data.status === "ok") {
            window.location.reload();
        } else {
            alert("Error removing reservation");
            console.error("[ERROR] /signup/remove", data);
            document.querySelector("#reservation-modal-footer > span:nth-child(1) > i:nth-child(1)").classList.add("is-hidden");
        }
    });


}

document.getElementById("is-save-reservation-trigger").addEventListener("click", saveReservation);
document.getElementById("is-edit-reservation-trigger").addEventListener("click", updateReservation);
document.getElementById("is-remove-reservation-trigger").addEventListener("click", removeReservation);




/*
 * Code to open modal dialog for adding a new reservation to the database.
 */


var allAddButtons = document.querySelectorAll(".is-signup-trigger");

for(var i = 0; i<allAddButtons.length; i++) {
    allAddButtons[i].addEventListener("click", displayNewReservationModal);
}

/*
 * Code to close modal dialogs.
 */


var allCloseModalButtons = document.querySelectorAll(".has-modal-close-behavior");

for(var i = 0; i<allCloseModalButtons.length; i++) {
    allCloseModalButtons[i].addEventListener("click", function() {
        var modalId = this.getAttribute("data-modal");
        document.getElementById(modalId).classList.remove("is-active");
    });
}

/*
 * Code to open modal dialog for editing a reservation.
 */


var allAddButtons = document.querySelectorAll(".is-edit-trigger");

for(var i = 0; i<allAddButtons.length; i++) {
    allAddButtons[i].addEventListener("click", displayEditReservationModal);
}


/*
 * Initialize pickers
 */

 var timepickerConfiguration = {
     step: 30,
     disableTextInput: true,
     'minTime': '6:00am',
    'maxTime': '6:00pm',
 };

$(".timepicker").timepicker(timepickerConfiguration);
$(".datepicker").flatpickr();
