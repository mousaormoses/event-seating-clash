(function () {
    const config = window.escSeatDesigner || {};
    const app = document.getElementById('esc-seat-designer-app');

    if ( ! app ) {
        return;
    }

    const strings = config.strings || {};
    const seatTypes = config.seatTypes || {};
    const defaultSeatType = Object.keys(seatTypes)[0] || 'regular';

    const escapeHtml = ( value ) => {
        return String( value )
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    if ( config.error ) {
        app.innerHTML = '<div class="notice notice-error"><p>' + escapeHtml( config.error ) + '</p></div>';
        return;
    }

    const normalizeSeat = ( seat ) => ({
        code: seat && seat.code ? String( seat.code ) : '',
        seat_label: seat && seat.seat_label ? String( seat.seat_label ) : '',
        type: seat && seat.type ? String( seat.type ) : defaultSeatType,
    });

    const normalizeRow = ( row ) => ({
        id: row && row.id ? String( row.id ) : '',
        label: row && row.label ? String( row.label ) : '',
        offset: row && Number.isFinite( Number( row.offset ) ) ? Number( row.offset ) : 0,
        seats: Array.isArray( row && row.seats ) ? row.seats.map( normalizeSeat ) : [],
    });

    const normalizeSection = ( section ) => ({
        id: section && section.id ? String( section.id ) : '',
        name: section && section.name ? String( section.name ) : '',
        rows: Array.isArray( section && section.rows ) ? section.rows.map( normalizeRow ) : [],
    });

    const state = {
        sections: Array.isArray( config.seatMap && config.seatMap.sections )
            ? config.seatMap.sections.map( normalizeSection )
            : [],
        status: '',
        statusType: '',
        isSaving: false,
    };

    const alphabetLabel = ( index ) => {
        let value = index;
        let label = '';

        do {
            label = String.fromCharCode( 65 + ( value % 26 ) ) + label;
            value = Math.floor( value / 26 ) - 1;
        } while ( value >= 0 );

        return label;
    };

    const ensureSectionRows = ( sectionIndex ) => {
        const section = state.sections[ sectionIndex ];
        if ( ! section ) {
            return;
        }

        if ( ! Array.isArray( section.rows ) ) {
            section.rows = [];
        }

        section.rows = section.rows.map( normalizeRow );
    };

    const addSection = () => {
        state.sections.push( {
            id: 'section-' + ( state.sections.length + 1 ),
            name: '',
            rows: [],
        } );
        render();
    };

    const removeSection = ( sectionIndex ) => {
        state.sections.splice( sectionIndex, 1 );
        render();
    };

    const addRow = ( sectionIndex ) => {
        ensureSectionRows( sectionIndex );
        const section = state.sections[ sectionIndex ];
        const label = alphabetLabel( section.rows.length );

        section.rows.push( {
            id: section.id ? section.id + '-row-' + ( section.rows.length + 1 ) : '',
            label,
            offset: 0,
            seats: [],
        } );

        render();
    };

    const removeRow = ( sectionIndex, rowIndex ) => {
        const section = state.sections[ sectionIndex ];
        if ( ! section || ! Array.isArray( section.rows ) ) {
            return;
        }

        section.rows.splice( rowIndex, 1 );
        render();
    };

    const addSeat = ( sectionIndex, rowIndex ) => {
        const section = state.sections[ sectionIndex ];
        if ( ! section || ! Array.isArray( section.rows ) ) {
            return;
        }

        const row = section.rows[ rowIndex ];
        if ( ! row || ! Array.isArray( row.seats ) ) {
            return;
        }

        const seatNumber = row.seats.length + 1;
        row.seats.push( {
            code: '',
            seat_label: String( seatNumber ),
            type: defaultSeatType,
        } );

        render();
    };

    const removeSeat = ( sectionIndex, rowIndex, seatIndex ) => {
        const section = state.sections[ sectionIndex ];
        if ( ! section ) {
            return;
        }

        const row = section.rows[ rowIndex ];
        if ( ! row || ! Array.isArray( row.seats ) ) {
            return;
        }

        row.seats.splice( seatIndex, 1 );
        render();
    };

    const updateStatus = ( message, type ) => {
        state.status = message;
        state.statusType = type || '';
        render();
    };

    const handleSave = () => {
        if ( state.isSaving ) {
            return;
        }

        state.isSaving = true;
        updateStatus( '', '' );

        const payload = {
            sections: state.sections.map( ( section ) => ( {
                id: section.id || '',
                name: section.name || '',
                rows: ( section.rows || [] ).map( ( row ) => ( {
                    id: row.id || '',
                    label: row.label || '',
                    offset: Number.isFinite( Number( row.offset ) ) ? Number( row.offset ) : 0,
                    seats: ( row.seats || [] ).map( ( seat ) => ( {
                        code: seat.code || '',
                        seat_label: seat.seat_label || '',
                        type: seat.type || defaultSeatType,
                    } ) ),
                } ) ),
            } ) ),
        };

        const body = new FormData();
        body.append( 'action', 'esc_save_seat_map' );
        body.append( 'nonce', config.nonce || '' );
        body.append( 'event_id', String( config.eventId || '' ) );
        body.append( 'seat_map', JSON.stringify( payload ) );

        fetch( config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body,
        } )
            .then( ( response ) => response.json() )
            .then( ( result ) => {
                state.isSaving = false;

                if ( ! result || ! result.success ) {
                    const message = result && result.data && result.data.message
                        ? result.data.message
                        : ( strings.saveFailure || 'Unable to save the seat map.' );
                    updateStatus( message, 'error' );
                    return;
                }

                const seatMap = result.data && result.data.seatMap ? result.data.seatMap : payload;
                state.sections = Array.isArray( seatMap.sections ) ? seatMap.sections.map( normalizeSection ) : [];
                updateStatus( strings.saveSuccess || 'Seat map saved successfully.', 'success' );
            } )
            .catch( () => {
                state.isSaving = false;
                updateStatus( strings.saveFailure || 'Unable to save the seat map. Please try again.', 'error' );
            } );
    };

    const renderSeatTypeOptions = ( selected ) => {
        return Object.entries( seatTypes )
            .map( ( [ key, label ] ) => {
                const isSelected = key === selected ? ' selected' : '';
                return '<option value="' + escapeHtml( key ) + '"' + isSelected + '>' + escapeHtml( label ) + '</option>';
            } )
            .join( '' );
    };

    const render = () => {
        const sectionsHtml = state.sections.map( ( section, sectionIndex ) => {
            const rowsHtml = ( section.rows || [] ).map( ( row, rowIndex ) => {
                const seatsHtml = ( row.seats || [] ).map( ( seat, seatIndex ) => {
                    return (
                        '<div class="esc-designer-seat" data-seat-index="' + seatIndex + '">' +
                            '<div class="esc-designer-seat__fields">' +
                                '<label>' + escapeHtml( strings.seatLabel || 'Seat Label' ) +
                                    '<input type="text" data-field="seat-label" value="' + escapeHtml( seat.seat_label || '' ) + '" />' +
                                '</label>' +
                                '<label>' + escapeHtml( strings.seatType || 'Seat Type' ) +
                                    '<select data-field="seat-type">' + renderSeatTypeOptions( seat.type ) + '</select>' +
                                '</label>' +
                            '</div>' +
                            '<button type="button" class="button button-link-delete" data-action="remove-seat">' + escapeHtml( strings.remove || 'Remove' ) + '</button>' +
                        '</div>'
                    );
                } ).join( '' );

                const emptyState = ( row.seats || [] ).length === 0
                    ? '<p class="esc-designer-row__empty">' + escapeHtml( strings.walkwayHelp || 'Use row offsets or empty rows to create aisles.' ) + '</p>'
                    : '';

                return (
                    '<div class="esc-designer-row" data-row-index="' + rowIndex + '">' +
                        '<div class="esc-designer-row__header">' +
                            '<label>' + escapeHtml( strings.rowLabel || 'Row Label' ) +
                                '<input type="text" data-field="row-label" value="' + escapeHtml( row.label || '' ) + '" />' +
                            '</label>' +
                            '<label>' + escapeHtml( strings.rowOffset || 'Row Offset' ) +
                                '<input type="number" min="0" step="1" data-field="row-offset" value="' + escapeHtml( row.offset || 0 ) + '" />' +
                            '</label>' +
                            '<div class="esc-designer-row__actions">' +
                                '<button type="button" class="button" data-action="add-seat">' + escapeHtml( strings.addSeat || 'Add Seat' ) + '</button>' +
                                '<button type="button" class="button button-link-delete" data-action="remove-row">' + escapeHtml( strings.remove || 'Remove' ) + '</button>' +
                            '</div>' +
                        '</div>' +
                        '<div class="esc-designer-row__seats">' + seatsHtml + emptyState + '</div>' +
                    '</div>'
                );
            } ).join( '' );

            return (
                '<div class="esc-designer-section" data-section-index="' + sectionIndex + '">' +
                    '<div class="esc-designer-section__header">' +
                        '<label>' + escapeHtml( strings.sectionName || 'Section Name' ) +
                            '<input type="text" data-field="section-name" value="' + escapeHtml( section.name || '' ) + '" />' +
                        '</label>' +
                        '<div class="esc-designer-section__actions">' +
                            '<button type="button" class="button" data-action="add-row">' + escapeHtml( strings.addRow || 'Add Row' ) + '</button>' +
                            '<button type="button" class="button button-link-delete" data-action="remove-section">' + escapeHtml( strings.remove || 'Remove' ) + '</button>' +
                        '</div>' +
                    '</div>' +
                    '<div class="esc-designer-section__rows">' + rowsHtml + '</div>' +
                '</div>'
            );
        } ).join( '' );

        const emptyNotice = state.sections.length === 0
            ? '<p class="esc-designer-empty">' + escapeHtml( strings.emptyNotice || 'Add sections, rows, and seats to build your layout.' ) + '</p>'
            : '';

        const statusClass = state.statusType ? ' esc-designer-status--' + state.statusType : '';
        const statusMarkup = state.status
            ? '<p class="esc-designer-status' + statusClass + '">' + escapeHtml( state.status ) + '</p>'
            : '';

        const saveLabel = state.isSaving
            ? escapeHtml( strings.saving || 'Saving…' )
            : escapeHtml( strings.saveChanges || 'Save Seating Layout' );

        app.innerHTML = (
            '<div class="esc-designer-sections">' + sectionsHtml + emptyNotice + '</div>' +
            '<p><button type="button" class="button button-secondary" data-action="add-section">' + escapeHtml( strings.addSection || 'Add Section' ) + '</button></p>' +
            '<div class="esc-designer-actions">' +
                '<button type="button" class="button button-primary" data-action="save"' + ( state.isSaving ? ' disabled' : '' ) + '>' + saveLabel + '</button>' +
                statusMarkup +
            '</div>'
        );
    };

    app.addEventListener( 'click', ( event ) => {
        const target = event.target;
        const action = target.getAttribute( 'data-action' );

        if ( ! action ) {
            return;
        }

        event.preventDefault();

        if ( 'add-section' === action ) {
            addSection();
            return;
        }

        const sectionNode = target.closest( '[data-section-index]' );
        const rowNode = target.closest( '[data-row-index]' );
        const seatNode = target.closest( '[data-seat-index]' );

        const sectionIndex = sectionNode ? parseInt( sectionNode.getAttribute( 'data-section-index' ), 10 ) : -1;
        const rowIndex = rowNode ? parseInt( rowNode.getAttribute( 'data-row-index' ), 10 ) : -1;
        const seatIndex = seatNode ? parseInt( seatNode.getAttribute( 'data-seat-index' ), 10 ) : -1;

        if ( Number.isNaN( sectionIndex ) || sectionIndex < 0 ) {
            if ( 'save' === action ) {
                handleSave();
            }
            return;
        }

        switch ( action ) {
        case 'remove-section':
            removeSection( sectionIndex );
            break;
        case 'add-row':
            addRow( sectionIndex );
            break;
        case 'remove-row':
            if ( rowIndex >= 0 ) {
                removeRow( sectionIndex, rowIndex );
            }
            break;
        case 'add-seat':
            if ( rowIndex >= 0 ) {
                addSeat( sectionIndex, rowIndex );
            }
            break;
        case 'remove-seat':
            if ( rowIndex >= 0 && seatIndex >= 0 ) {
                removeSeat( sectionIndex, rowIndex, seatIndex );
            }
            break;
        case 'save':
            handleSave();
            break;
        default:
            break;
        }
    } );

    const handleFieldUpdate = ( target ) => {
        const field = target.getAttribute( 'data-field' );
        if ( ! field ) {
            return;
        }

        const sectionNode = target.closest( '[data-section-index]' );
        const rowNode = target.closest( '[data-row-index]' );
        const seatNode = target.closest( '[data-seat-index]' );

        const sectionIndex = sectionNode ? parseInt( sectionNode.getAttribute( 'data-section-index' ), 10 ) : -1;
        const rowIndex = rowNode ? parseInt( rowNode.getAttribute( 'data-row-index' ), 10 ) : -1;
        const seatIndex = seatNode ? parseInt( seatNode.getAttribute( 'data-seat-index' ), 10 ) : -1;

        const section = state.sections[ sectionIndex ];
        if ( ! section ) {
            return;
        }

        if ( 'section-name' === field ) {
            section.name = target.value;
            return;
        }

        const row = rowIndex >= 0 && section.rows ? section.rows[ rowIndex ] : null;
        if ( ! row ) {
            return;
        }

        switch ( field ) {
        case 'row-label':
            row.label = target.value;
            break;
        case 'row-offset':
            row.offset = Math.max( 0, parseInt( target.value, 10 ) || 0 );
            target.value = row.offset;
            break;
        case 'seat-label':
            if ( seatIndex >= 0 && row.seats && row.seats[ seatIndex ] ) {
                row.seats[ seatIndex ].seat_label = target.value;
            }
            break;
        case 'seat-type':
            if ( seatIndex >= 0 && row.seats && row.seats[ seatIndex ] ) {
                row.seats[ seatIndex ].type = target.value;
            }
            break;
        default:
            break;
        }
    };

    app.addEventListener( 'input', ( event ) => {
        handleFieldUpdate( event.target );
    } );

    app.addEventListener( 'change', ( event ) => {
        handleFieldUpdate( event.target );
    } );

    render();
})();
