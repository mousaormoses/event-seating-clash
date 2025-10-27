jQuery(document).ready(function($) {
    let selectedSeats = [];

    $('.esbp-seat-map-frontend td').on('click', function() {
        if ( ! $(this).hasClass('seat-disabled') && ! $(this).hasClass('seat-booked') ) {
            $(this).toggleClass('selected');
            const seatId = $(this).text();

            if ($(this).hasClass('selected')) {
                selectedSeats.push(seatId);
            } else {
                selectedSeats = selectedSeats.filter(seat => seat !== seatId);
            }
            $('#esbp_selected_seats').val(JSON.stringify(selectedSeats));
        }
    });

    $('#esbp-booking-form').on('submit', function(e) {
        e.preventDefault();

        const formData = {
            action: 'esbp_book_seats',
            esbp_book_seats_nonce: $('#esbp_book_seats_nonce').val(),
            event_id: $('[name="esbp_event_id"]').val(),
            seat_map_id: $('[name="esbp_seat_map_id"]').val(),
            selected_seats: $('#esbp_selected_seats').val(),
            user_name: $('#esbp_user_name').val(),
            user_email: $('#esbp_user_email').val(),
        };

        $.post(esbp_ajax.ajax_url, formData, function(response) {
            if (response.success) {
                $('#esbp-booking-response').html('<p class="success">' + response.data.message + '</p>');
                selectedSeats.forEach(seatId => {
                    $('.esbp-seat-map-frontend td').filter(function() {
                        return $(this).text() === seatId;
                    }).removeClass('selected').addClass('seat-booked');
                });
                selectedSeats = [];
                $('#esbp_selected_seats').val('');
            } else {
                $('#esbp-booking-response').html('<p class="error">' + response.data.message + '</p>');
            }
        });
    });
});
