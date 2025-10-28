jQuery(document).ready(function ($) {
    const canvas = $('#esc-editor-canvas');
    const addSeatBtn = $('#esc-add-seat-btn');
    const saveMapBtn = $('#esc-save-map-btn');
    const eventSelect = $('#esc-event-select');
    const propertiesForm = $('#esc-seat-properties-form');
    const noSeatSelected = $('#esc-no-seat-selected');
    const seatTypeSelect = $('#esc-seat-type');
    const seatLabelInput = $('#esc-seat-label');
    const alignHorizontalBtn = $('#esc-align-horizontal-btn');
    const alignVerticalBtn = $('#esc-align-vertical-btn');
    let seats = [];
    let seatCounter = 1;
    let selectedSeat = null;
    const selectedSeatIds = new Set();

    function getSeatById(id) {
        return seats.find((seat) => seat.id === id);
    }

    function refreshSelectedSeatReference() {
        if (selectedSeat && selectedSeatIds.has(selectedSeat.id)) {
            return;
        }

        const lastId = Array.from(selectedSeatIds).pop();
        selectedSeat = lastId ? getSeatById(lastId) : null;
    }

    function updatePropertiesPanel() {
        if (selectedSeat) {
            noSeatSelected.hide();
            propertiesForm.show();
            seatTypeSelect.val(selectedSeat.type);
            seatLabelInput.val(selectedSeat.label);
        } else {
            propertiesForm.hide();
            noSeatSelected.show();
        }
    }

    function updateAlignmentButtons() {
        const hasMultipleSelection = selectedSeatIds.size >= 2;
        alignHorizontalBtn.prop('disabled', !hasMultipleSelection);
        alignVerticalBtn.prop('disabled', !hasMultipleSelection);
    }

    function clearSelection(shouldUpdateUI = true) {
        selectedSeatIds.clear();
        selectedSeat = null;
        $('.esc-seat-draggable').removeClass('is-selected');

        if (shouldUpdateUI) {
            updatePropertiesPanel();
            updateAlignmentButtons();
        }
    }

    function selectSeat(seat, element, event = null) {
        if (!seat || !element) {
            return;
        }

        const allowMultiple = event && (event.ctrlKey || event.metaKey || event.shiftKey);

        if (!allowMultiple) {
            clearSelection(false);
        }

        const alreadySelected = selectedSeatIds.has(seat.id);

        if (allowMultiple && alreadySelected) {
            selectedSeatIds.delete(seat.id);
            element.removeClass('is-selected');
        } else {
            selectedSeatIds.add(seat.id);
            element.addClass('is-selected');
            selectedSeat = seat;
        }

        refreshSelectedSeatReference();
        updatePropertiesPanel();
        updateAlignmentButtons();
    }

    canvas.on('click', function (event) {
        if (event.target === canvas[0]) {
            clearSelection();
        }
    });

    // Load seat map for the selected event
    eventSelect.on('change', function () {
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
            clearSelection();
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
            success: function (response) {
                if (response.success) {
                    canvas.empty();
                    clearSelection();
                    seats = (response.data.seat_map || []).map((seat) => ({
                        ...seat,
                        top: parseInt(seat.top, 10) || 0,
                        left: parseInt(seat.left, 10) || 0,
                    }));
                    seatCounter = seats.length ? Math.max(...seats.map((s) => parseInt(s.label, 10) || 0)) + 1 : 1;
                    seats.forEach((seat) => renderSeat(seat));
                    updateAlignmentButtons();
                } else {
                    alert('Error loading seat map: ' + response.data.message);
                }
            },
            error: function () {
                alert('An unknown error occurred while loading the seat map.');
            }
        });
    }

    // Add a new seat to the canvas
    addSeatBtn.on('click', function () {
        const seatId = `seat-${Date.now()}`;
        const newSeat = {
            id: seatId,
            top: 50,
            left: 50,
            type: 'regular',
            label: `${seatCounter}`,
        };
        seats.push(newSeat);
        const seatElement = renderSeat(newSeat);
        selectSeat(newSeat, seatElement);
        seatCounter++;
    });

    // Save the seat map
    saveMapBtn.on('click', function () {
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
            success: function (response) {
                if (response.success) {
                    alert('Seat map saved successfully!');
                } else {
                    alert('Error saving seat map: ' + response.data.message);
                }
            },
            error: function () {
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
        seatElement.on('click', (event) => {
            event.stopPropagation();
            selectSeat(seat, seatElement, event);
        });

        return seatElement;
    }

    // Make a seat element draggable
    function makeDraggable(element) {
        element.on('mousedown', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const seatElement = $(this);
            const seatId = seatElement.attr('id');
            const seat = getSeatById(seatId);

            if (!seat) {
                return;
            }

            if (!selectedSeatIds.has(seat.id)) {
                selectSeat(seat, seatElement, e);
            }

            const initialX = e.clientX - seat.left;
            const initialY = e.clientY - seat.top;

            $(document).on('mousemove', function (moveEvent) {
                let newLeft = moveEvent.clientX - initialX;
                let newTop = moveEvent.clientY - initialY;

                newLeft = Math.max(0, Math.min(newLeft, canvas.width() - element.outerWidth()));
                newTop = Math.max(0, Math.min(newTop, canvas.height() - element.outerHeight()));

                element.css({
                    left: newLeft,
                    top: newTop,
                });
            });

            $(document).on('mouseup', function () {
                $(document).off('mousemove');
                $(document).off('mouseup');

                seat.left = parseInt(element.css('left'), 10);
                seat.top = parseInt(element.css('top'), 10);
            });
        });
    }

    function alignSelectedSeatsHorizontally() {
        const ids = Array.from(selectedSeatIds);

        if (ids.length < 2) {
            return;
        }

        const referenceSeat = getSeatById(ids[0]);

        if (!referenceSeat) {
            return;
        }

        const targetTop = parseInt(referenceSeat.top, 10) || 0;

        ids.forEach((id) => {
            const seat = getSeatById(id);

            if (!seat) {
                return;
            }

            seat.top = targetTop;
            $(`#${seat.id}`).css('top', targetTop);
        });
    }

    function alignSelectedSeatsVertically() {
        const ids = Array.from(selectedSeatIds);

        if (ids.length < 2) {
            return;
        }

        const referenceSeat = getSeatById(ids[0]);

        if (!referenceSeat) {
            return;
        }

        const targetLeft = parseInt(referenceSeat.left, 10) || 0;

        ids.forEach((id) => {
            const seat = getSeatById(id);

            if (!seat) {
                return;
            }

            seat.left = targetLeft;
            $(`#${seat.id}`).css('left', targetLeft);
        });
    }

    // Update seat properties
    seatTypeSelect.on('change', function () {
        if (selectedSeat) {
            selectedSeat.type = $(this).val();
        }
    });

    seatLabelInput.on('input', function () {
        if (selectedSeat) {
            selectedSeat.label = $(this).val();
            $(`#${selectedSeat.id}`).text(selectedSeat.label);
        }
    });

    alignHorizontalBtn.on('click', function (event) {
        event.preventDefault();
        alignSelectedSeatsHorizontally();
    });

    alignVerticalBtn.on('click', function (event) {
        event.preventDefault();
        alignSelectedSeatsVertically();
    });

    updatePropertiesPanel();
    updateAlignmentButtons();
});
