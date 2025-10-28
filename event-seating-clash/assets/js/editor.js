jQuery(document).ready(function($) {
    const canvas = $('#esc-editor-canvas');
    const addSeatBtn = $('#esc-add-seat-btn');
    const saveMapBtn = $('#esc-save-map-btn');
    const eventSelect = $('#esc-event-select');
    const propertiesForm = $('#esc-seat-properties-form');
    const noSeatSelected = $('#esc-no-seat-selected');
    const seatTypeSelect = $('#esc-seat-type');
    const seatLabelInput = $('#esc-seat-label');
    let seats = [];
    let seatCounter = 1;
    let selectedSeat = null;

    // Load seat map for the selected event
    eventSelect.on('change', function() {
        const eventId = $(this).val();
        if (eventId) {
            addSeatBtn.prop('disabled', false);
            saveMapBtn.prop('disabled', false);
            loadSeatMap(eventId);
        } else {
            addSeatBtn.prop('disabled', true);
            saveMapBtn.prop('disabled', true);
            canvas.empty();
            seats = [];
            seatCounter = 1;
            selectedSeat = null;
        }
    });

    function loadSeatMap(eventId) {
        $.ajax({
            url: escEditor.ajax_url,
            type: 'GET',
            data: {
                action: 'esc_load_seat_map',
                nonce: escEditor.nonce,
                event_id: eventId,
            },
            success: function(response) {
                if (response.success) {
                    canvas.empty();
                    seats = response.data.seat_map || [];
                    seatCounter = seats.length ? Math.max(...seats.map(s => parseInt(s.label) || 0)) + 1 : 1;
                    seats.forEach(renderSeat);
                } else {
                    alert('Error loading seat map: ' + response.data.message);
                }
            },
            error: function() {
                alert('An unknown error occurred while loading the seat map.');
            }
        });
    }

    // Add a new seat to the canvas
    addSeatBtn.on('click', function() {
        const seatId = `seat-${Date.now()}`;
        const newSeat = {
            id: seatId,
            top: 50,
            left: 50,
            type: 'regular',
            label: `${seatCounter}`,
        };
        seats.push(newSeat);
        renderSeat(newSeat);
        seatCounter++;
    });

    // Save the seat map
    saveMapBtn.on('click', function() {
        const eventId = eventSelect.val();
        if (!eventId) {
            alert('Please select an event first.');
            return;
        }

        $.ajax({
            url: escEditor.ajax_url,
            type: 'POST',
            data: {
                action: 'esc_save_seat_map',
                nonce: escEditor.nonce,
                event_id: eventId,
                seats: JSON.stringify(seats),
            },
            success: function(response) {
                if (response.success) {
                    alert('Seat map saved successfully!');
                } else {
                    alert('Error saving seat map: ' + response.data.message);
                }
            },
            error: function() {
                alert('An unknown error occurred while saving the seat map.');
            }
        });
    });

    // Render a single seat on the canvas
    function renderSeat(seat) {
        const seatElement = $('<div>', {
            id: seat.id,
            class: 'esc-seat-draggable',
            css: {
                top: seat.top,
                left: seat.left,
            },
            text: seat.label,
        });

        canvas.append(seatElement);
        makeDraggable(seatElement);
        seatElement.on('click', () => selectSeat(seat, seatElement));
    }

    // Make a seat element draggable
    function makeDraggable(element) {
        element.on('mousedown', function(e) {
            const seatId = $(this).attr('id');
            const seat = seats.find(s => s.id === seatId);
            const initialX = e.clientX - seat.left;
            const initialY = e.clientY - seat.top;

            $(document).on('mousemove', function(e) {
                let newLeft = e.clientX - initialX;
                let newTop = e.clientY - initialY;

                newLeft = Math.max(0, Math.min(newLeft, canvas.width() - element.outerWidth()));
                newTop = Math.max(0, Math.min(newTop, canvas.height() - element.outerHeight()));

                element.css({
                    left: newLeft,
                    top: newTop,
                });
            });

            $(document).on('mouseup', function() {
                $(document).off('mousemove');
                $(document).off('mouseup');

                seat.left = parseInt(element.css('left'));
                seat.top = parseInt(element.css('top'));
            });
        });
    }

    // Select a seat and show its properties
    function selectSeat(seat, element) {
        selectedSeat = seat;
        $('.esc-seat-draggable').removeClass('is-selected');
        element.addClass('is-selected');

        noSeatSelected.hide();
        propertiesForm.show();

        seatTypeSelect.val(seat.type);
        seatLabelInput.val(seat.label);
    }

    // Update seat properties
    seatTypeSelect.on('change', function() {
        if (selectedSeat) {
            selectedSeat.type = $(this).val();
        }
    });

    seatLabelInput.on('input', function() {
        if (selectedSeat) {
            selectedSeat.label = $(this).val();
            $(`#${selectedSeat.id}`).text(selectedSeat.label);
        }
    });
});
