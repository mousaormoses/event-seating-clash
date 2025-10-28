(function () {
    const config = window.escSeatDesigner || {};
    const app = document.getElementById('esc-seat-designer-app');

    if (!app) {
        return;
    }

    const strings = config.strings || {};

    const escapeHtml = (value) => {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    const slugify = (value) => {
        return String(value)
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-');
    };

    if (config.error) {
        app.innerHTML = '<div class="notice notice-error"><p>' + escapeHtml(config.error) + '</p></div>';
        return;
    }

    const normalizeSeatType = (type, fallbackIndex) => {
        if (!type) {
            return null;
        }

        if (typeof type === 'string') {
            return {
                id: slugify(type || 'type-' + fallbackIndex),
                label: String(type),
            };
        }

        const id = type.id ? slugify(type.id) : slugify(type.key || 'type-' + fallbackIndex);
        const label = type.label ? String(type.label) : String(type.name || type.id || 'Type ' + (fallbackIndex + 1));

        if (!id || !label) {
            return null;
        }

        return { id, label };
    };

    const normalizeGroup = (group, index) => {
        if (!group) {
            return null;
        }

        const id = group.id ? slugify(group.id) : 'group-' + (index + 1);
        const name = group.name ? String(group.name) : 'Group ' + (index + 1);
        const prefixSource = group.prefix ? String(group.prefix) : name;
        const prefix = prefixSource.replace(/[^A-Za-z0-9]/g, '').toUpperCase() || ('G' + (index + 1));

        return {
            id,
            name,
            prefix,
        };
    };

    const normalizeSeat = (seat, defaultType) => ({
        code: seat && seat.code ? String(seat.code) : '',
        seat_label: seat && seat.seat_label ? String(seat.seat_label) : '',
        type: seat && seat.type ? String(seat.type) : defaultType,
        group: seat && seat.group ? String(seat.group) : '',
    });

    const normalizeRow = (row, defaultType) => ({
        id: row && row.id ? String(row.id) : '',
        label: row && row.label ? String(row.label) : '',
        offset: row && Number.isFinite(Number(row.offset)) ? Number(row.offset) : 0,
        seats: Array.isArray(row && row.seats) ? row.seats.map((seat) => normalizeSeat(seat, defaultType)) : [],
    });

    const normalizeSection = (section, defaultType) => ({
        id: section && section.id ? String(section.id) : '',
        name: section && section.name ? String(section.name) : '',
        rows: Array.isArray(section && section.rows) ? section.rows.map((row) => normalizeRow(row, defaultType)) : [],
    });

    const normalizeSettings = (settings) => {
        const normalized = {
            seat_size: 60,
        };

        if (settings && Number.isFinite(Number(settings.seat_size))) {
            const size = Math.max(24, Math.min(160, parseInt(settings.seat_size, 10)));
            normalized.seat_size = size;
        }

        return normalized;
    };

    const mapSeatTypes = config.seatMap && config.seatMap.seat_types ? config.seatMap.seat_types : (config.seatTypes || {});
    const initialSeatTypes = Object.keys(mapSeatTypes).map((key, index) => normalizeSeatType({ id: key, label: mapSeatTypes[key] }, index)).filter(Boolean);
    const fallbackSeatTypes = Object.keys(config.seatTypes || {}).map((key, index) => normalizeSeatType({ id: key, label: config.seatTypes[key] }, index)).filter(Boolean);

    if (initialSeatTypes.length === 0 && fallbackSeatTypes.length > 0) {
        initialSeatTypes.push(...fallbackSeatTypes);
    }

    if (initialSeatTypes.length === 0) {
        initialSeatTypes.push({ id: 'regular', label: strings.defaultTypeRegular || 'Regular' });
    }

    const initialGroups = Array.isArray(config.seatMap && config.seatMap.groups)
        ? config.seatMap.groups.map((group, index) => normalizeGroup(group, index)).filter(Boolean)
        : [];

    const defaultSeatTypeId = initialSeatTypes[0].id;

    const state = {
        sections: Array.isArray(config.seatMap && config.seatMap.sections)
            ? config.seatMap.sections.map((section) => normalizeSection(section, defaultSeatTypeId))
            : [],
        seatTypes: initialSeatTypes,
        groups: initialGroups,
        settings: normalizeSettings(config.seatMap && config.seatMap.settings),
        selectedSeats: new Set(),
        status: '',
        statusType: '',
        isSaving: false,
    };

    const getSeatTypeMap = () => {
        return state.seatTypes.reduce((acc, type) => {
            acc[type.id] = type.label;
            return acc;
        }, {});
    };

    let seatTypeMap = getSeatTypeMap();

    const updateSeatTypeMap = () => {
        seatTypeMap = getSeatTypeMap();
    };

    const ensureSeatTypeExists = (typeId) => {
        if (seatTypeMap[typeId]) {
            return typeId;
        }
        return state.seatTypes[0] ? state.seatTypes[0].id : typeId;
    };

    const coerceSeatTypes = () => {
        updateSeatTypeMap();
        const validGroups = new Set(state.groups.map((group) => group.id));

        state.sections.forEach((section) => {
            (section.rows || []).forEach((row) => {
                (row.seats || []).forEach((seat) => {
                    seat.type = ensureSeatTypeExists(seat.type);

                    if (seat.group && !validGroups.has(seat.group)) {
                        seat.group = '';
                    }
                });
            });
        });
    };

    const keyForSeat = (sectionIndex, rowIndex, seatIndex) => `${sectionIndex}:${rowIndex}:${seatIndex}`;

    const resetSeatLabel = (sectionIndex, rowIndex, seatIndex) => {
        const section = state.sections[sectionIndex];
        if (!section) {
            return;
        }
        const row = section.rows && section.rows[rowIndex];
        if (!row) {
            return;
        }
        const seat = row.seats && row.seats[seatIndex];
        if (!seat) {
            return;
        }
        seat.seat_label = String(seatIndex + 1);
    };

    const renumberGroupSeats = (groupId) => {
        if (!groupId) {
            return;
        }

        const group = state.groups.find((item) => item.id === groupId);
        if (!group) {
            return;
        }

        let counter = 0;
        state.sections.forEach((section) => {
            (section.rows || []).forEach((row) => {
                (row.seats || []).forEach((seat) => {
                    if (seat.group === groupId) {
                        counter += 1;
                        seat.seat_label = `${group.prefix || group.name || group.id}-${counter}`;
                    }
                });
            });
        });
    };

    const renumberAllGroups = () => {
        state.groups.forEach((group) => renumberGroupSeats(group.id));
    };

    coerceSeatTypes();
    renumberAllGroups();

    const alphabetLabel = (index) => {
        let value = index;
        let label = '';

        do {
            label = String.fromCharCode(65 + (value % 26)) + label;
            value = Math.floor(value / 26) - 1;
        } while (value >= 0);

        return label;
    };

    const ensureSectionRows = (sectionIndex) => {
        const section = state.sections[sectionIndex];
        if (!section) {
            return;
        }

        if (!Array.isArray(section.rows)) {
            section.rows = [];
        }

        section.rows = section.rows.map((row) => normalizeRow(row, ensureSeatTypeExists(row.seats && row.seats[0] ? row.seats[0].type : defaultSeatTypeId)));
    };

    const addSection = () => {
        state.sections.push({
            id: 'section-' + (state.sections.length + 1),
            name: '',
            rows: [],
        });
        render();
    };

    const removeSection = (sectionIndex) => {
        state.sections.splice(sectionIndex, 1);
        state.selectedSeats.clear();
        render();
    };

    const addRow = (sectionIndex) => {
        ensureSectionRows(sectionIndex);
        const section = state.sections[sectionIndex];
        if (!section) {
            return;
        }
        const label = alphabetLabel(section.rows.length);

        section.rows.push({
            id: section.id ? section.id + '-row-' + (section.rows.length + 1) : '',
            label,
            offset: 0,
            seats: [],
        });

        render();
    };

    const removeRow = (sectionIndex, rowIndex) => {
        const section = state.sections[sectionIndex];
        if (!section || !Array.isArray(section.rows)) {
            return;
        }

        section.rows.splice(rowIndex, 1);
        state.selectedSeats.clear();
        render();
    };

    const addSeat = (sectionIndex, rowIndex) => {
        const section = state.sections[sectionIndex];
        if (!section || !Array.isArray(section.rows)) {
            return;
        }

        const row = section.rows[rowIndex];
        if (!row || !Array.isArray(row.seats)) {
            return;
        }

        const seatNumber = row.seats.length + 1;
        const defaultType = state.seatTypes[0] ? state.seatTypes[0].id : defaultSeatTypeId;

        row.seats.push({
            code: '',
            seat_label: String(seatNumber),
            type: defaultType,
            group: '',
        });

        render();
    };

    const removeSeat = (sectionIndex, rowIndex, seatIndex) => {
        const section = state.sections[sectionIndex];
        if (!section) {
            return;
        }

        const row = section.rows && section.rows[rowIndex];
        if (!row || !Array.isArray(row.seats)) {
            return;
        }

        row.seats.splice(seatIndex, 1);
        render();
    };

    const getUsedSeatTypes = () => {
        const used = new Set();
        state.sections.forEach((section) => {
            (section.rows || []).forEach((row) => {
                (row.seats || []).forEach((seat) => {
                    if (seat.type) {
                        used.add(seat.type);
                    }
                });
            });
        });
        return used;
    };

    const getUsedGroups = () => {
        const used = new Set();
        state.sections.forEach((section) => {
            (section.rows || []).forEach((row) => {
                (row.seats || []).forEach((seat) => {
                    if (seat.group) {
                        used.add(seat.group);
                    }
                });
            });
        });
        return used;
    };

    const addSeatType = () => {
        const suffix = state.seatTypes.length + 1;
        let label = strings.newTypeLabel || 'Ticket Type ' + suffix;
        let id = slugify(label);

        while (seatTypeMap[id]) {
            id = slugify(label + '-' + suffix);
        }

        state.seatTypes.push({ id, label });
        updateSeatTypeMap();
        render();
    };

    const removeSeatType = (typeId) => {
        const used = getUsedSeatTypes();
        if (used.has(typeId) || state.seatTypes.length === 1) {
            return;
        }

        state.seatTypes = state.seatTypes.filter((type) => type.id !== typeId);
        updateSeatTypeMap();

        state.sections.forEach((section) => {
            (section.rows || []).forEach((row) => {
                (row.seats || []).forEach((seat) => {
                    if (seat.type === typeId) {
                        seat.type = ensureSeatTypeExists(seat.type);
                    }
                });
            });
        });

        render();
    };

    const addGroup = () => {
        const suffix = state.groups.length + 1;
        const id = 'group-' + suffix;
        const name = (strings.newGroupLabel || 'Group') + ' ' + suffix;
        const prefix = ('G' + suffix);

        state.groups.push({ id, name, prefix });
        render();
    };

    const removeGroup = (groupId) => {
        const used = getUsedGroups();
        if (used.has(groupId)) {
            return;
        }

        state.groups = state.groups.filter((group) => group.id !== groupId);

        state.sections.forEach((section, sectionIndex) => {
            (section.rows || []).forEach((row, rowIndex) => {
                (row.seats || []).forEach((seat, seatIndex) => {
                    if (seat.group === groupId) {
                        seat.group = '';
                        resetSeatLabel(sectionIndex, rowIndex, seatIndex);
                    }
                });
            });
        });

        renumberAllGroups();
        render();
    };

    const updateStatus = (message, type) => {
        state.status = message;
        state.statusType = type || '';
        render();
    };

    const clearSelection = () => {
        state.selectedSeats.clear();
    };

    const assignGroupToSelected = (groupId) => {
        const keys = Array.from(state.selectedSeats.values());

        keys.forEach((key) => {
            const [sectionIndex, rowIndex, seatIndex] = key.split(':').map((value) => parseInt(value, 10));
            const section = state.sections[sectionIndex];
            if (!section) {
                return;
            }
            const row = section.rows && section.rows[rowIndex];
            if (!row) {
                return;
            }
            const seat = row.seats && row.seats[seatIndex];
            if (!seat) {
                return;
            }

            if (!groupId) {
                seat.group = '';
                resetSeatLabel(sectionIndex, rowIndex, seatIndex);
            } else {
                seat.group = groupId;
            }
        });

        renumberAllGroups();
        render();
    };

    const handleSave = () => {
        if (state.isSaving) {
            return;
        }

        state.isSaving = true;
        updateStatus('', '');

        const payload = {
            sections: state.sections.map((section) => ({
                id: section.id || '',
                name: section.name || '',
                rows: (section.rows || []).map((row) => ({
                    id: row.id || '',
                    label: row.label || '',
                    offset: Number.isFinite(Number(row.offset)) ? Number(row.offset) : 0,
                    seats: (row.seats || []).map((seat) => ({
                        code: seat.code || '',
                        seat_label: seat.seat_label || '',
                        type: ensureSeatTypeExists(seat.type),
                        group: seat.group || '',
                    })),
                })),
            })),
            seat_types: state.seatTypes.map((type) => ({ id: type.id, label: type.label })),
            groups: state.groups.map((group) => ({ id: group.id, name: group.name, prefix: group.prefix })),
            settings: {
                seat_size: Number.isFinite(Number(state.settings.seat_size))
                    ? Math.max(24, Math.min(160, parseInt(state.settings.seat_size, 10)))
                    : 60,
            },
        };

        const body = new FormData();
        body.append('action', 'esc_save_seat_map');
        body.append('nonce', config.nonce || '');
        body.append('event_id', String(config.eventId || ''));
        body.append('seat_map', JSON.stringify(payload));

        fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body,
        })
            .then((response) => response.json())
            .then((result) => {
                state.isSaving = false;

                if (!result || !result.success) {
                    const message = result && result.data && result.data.message
                        ? result.data.message
                        : (strings.saveFailure || 'Unable to save the seat map.');
                    updateStatus(message, 'error');
                    return;
                }

                const seatMap = result.data && result.data.seatMap ? result.data.seatMap : payload;
                const newSeatTypes = seatMap.seat_types || payload.seat_types;
                const newGroups = seatMap.groups || payload.groups;
                const newSettings = seatMap.settings || payload.settings;

                state.sections = Array.isArray(seatMap.sections)
                    ? seatMap.sections.map((section) => normalizeSection(section, defaultSeatTypeId))
                    : [];

                state.seatTypes = Array.isArray(newSeatTypes)
                    ? newSeatTypes.map((type, index) => normalizeSeatType(type, index)).filter(Boolean)
                    : Object.keys(newSeatTypes || {}).map((key, index) => normalizeSeatType({ id: key, label: newSeatTypes[key] }, index)).filter(Boolean);

                if (state.seatTypes.length === 0) {
                    state.seatTypes.push({ id: 'regular', label: strings.defaultTypeRegular || 'Regular' });
                }

                state.groups = Array.isArray(newGroups)
                    ? newGroups.map((group, index) => normalizeGroup(group, index)).filter(Boolean)
                    : [];

                state.settings = normalizeSettings(newSettings);
                state.selectedSeats.clear();
                coerceSeatTypes();
                renumberAllGroups();
                updateStatus(strings.saveSuccess || 'Seat map saved successfully.', 'success');
            })
            .catch(() => {
                state.isSaving = false;
                updateStatus(strings.saveFailure || 'Unable to save the seat map. Please try again.', 'error');
            });
    };

    const renderSeatTypeOptions = (selected) => {
        return state.seatTypes
            .map((type) => {
                const isSelected = type.id === selected ? ' selected' : '';
                return '<option value="' + escapeHtml(type.id) + '"' + isSelected + '>' + escapeHtml(type.label) + '</option>';
            })
            .join('');
    };

    const renderGroupOptions = (selected) => {
        const options = ['<option value="">' + escapeHtml(strings.noGroup || 'No group') + '</option>'];
        state.groups.forEach((group) => {
            const isSelected = group.id === selected ? ' selected' : '';
            options.push('<option value="' + escapeHtml(group.id) + '"' + isSelected + '>' + escapeHtml(group.name) + '</option>');
        });
        return options.join('');
    };

    const renderSeatTypesPanel = () => {
        const usedTypes = getUsedSeatTypes();
        const rows = state.seatTypes
            .map((type) => {
                const disableRemoval = usedTypes.has(type.id) || state.seatTypes.length === 1;
                return (
                    '<div class="esc-designer-type" data-seat-type-id="' + escapeHtml(type.id) + '">' +
                        '<label>' + escapeHtml(strings.ticketTypeLabel || 'Label') +
                            '<input type="text" data-field="seat-type-label" value="' + escapeHtml(type.label) + '" />' +
                        '</label>' +
                        '<label>' + escapeHtml(strings.ticketTypeKey || 'Key') +
                            '<input type="text" data-field="seat-type-key" value="' + escapeHtml(type.id) + '" />' +
                        '</label>' +
                        '<button type="button" class="button button-link-delete" data-action="remove-seat-type"' + (disableRemoval ? ' disabled' : '') + '>' + escapeHtml(strings.remove || 'Remove') + '</button>' +
                    '</div>'
                );
            })
            .join('');

        return (
            '<section class="esc-designer-panel esc-designer-panel--types">' +
                '<header><h2>' + escapeHtml(strings.ticketTypesHeading || 'Ticket Types') + '</h2></header>' +
                '<div class="esc-designer-panel__body">' + rows + '</div>' +
                '<footer><button type="button" class="button" data-action="add-seat-type">' + escapeHtml(strings.addTicketType || 'Add Ticket Type') + '</button></footer>' +
            '</section>'
        );
    };

    const renderGroupsPanel = () => {
        const usedGroups = getUsedGroups();
        const rows = state.groups
            .map((group) => {
                const disableRemoval = usedGroups.has(group.id);
                return (
                    '<div class="esc-designer-group" data-seat-group-id="' + escapeHtml(group.id) + '">' +
                        '<label>' + escapeHtml(strings.groupName || 'Name') +
                            '<input type="text" data-field="seat-group-name" value="' + escapeHtml(group.name) + '" />' +
                        '</label>' +
                        '<label>' + escapeHtml(strings.groupPrefix || 'Prefix') +
                            '<input type="text" data-field="seat-group-prefix" value="' + escapeHtml(group.prefix) + '" />' +
                        '</label>' +
                        '<button type="button" class="button button-link-delete" data-action="remove-seat-group"' + (disableRemoval ? ' disabled' : '') + '>' + escapeHtml(strings.remove || 'Remove') + '</button>' +
                    '</div>'
                );
            })
            .join('');

        return (
            '<section class="esc-designer-panel esc-designer-panel--groups">' +
                '<header><h2>' + escapeHtml(strings.groupsHeading || 'Seat Groups') + '</h2></header>' +
                '<div class="esc-designer-panel__body">' + rows + '</div>' +
                '<footer><button type="button" class="button" data-action="add-seat-group">' + escapeHtml(strings.addGroup || 'Add Group') + '</button></footer>' +
            '</section>'
        );
    };

    const renderSettingsPanel = () => {
        return (
            '<section class="esc-designer-panel esc-designer-panel--settings">' +
                '<header><h2>' + escapeHtml(strings.settingsHeading || 'Seat Settings') + '</h2></header>' +
                '<div class="esc-designer-panel__body">' +
                    '<label>' + escapeHtml(strings.seatSize || 'Seat Size') +
                        '<input type="range" min="24" max="160" step="2" data-field="seat-size" value="' + escapeHtml(state.settings.seat_size) + '" />' +
                        '<span class="esc-designer-panel__hint">' + escapeHtml(strings.seatSizeHelp || 'Adjust the seat size to better fit your layout.') + '</span>' +
                    '</label>' +
                '</div>' +
            '</section>'
        );
    };

    const renderSelectionTools = () => {
        const selectedCount = state.selectedSeats.size;
        const groupOptions = renderGroupOptions('');

        return (
            '<section class="esc-designer-selection">' +
                '<div class="esc-designer-selection__summary">' +
                    '<strong>' + escapeHtml(strings.selectedSeats || 'Selected Seats') + ':</strong> ' + selectedCount +
                '</div>' +
                '<div class="esc-designer-selection__controls">' +
                    '<label>' + escapeHtml(strings.assignGroup || 'Assign to Group') +
                        '<select data-field="bulk-group">' + groupOptions + '</select>' +
                    '</label>' +
                    '<button type="button" class="button" data-action="apply-bulk-group"' + (selectedCount === 0 ? ' disabled' : '') + '>' + escapeHtml(strings.applyGroup || 'Apply') + '</button>' +
                    '<button type="button" class="button" data-action="clear-bulk-group"' + (selectedCount === 0 ? ' disabled' : '') + '>' + escapeHtml(strings.removeGroup || 'Remove Group') + '</button>' +
                    '<button type="button" class="button button-link" data-action="clear-selection"' + (selectedCount === 0 ? ' disabled' : '') + '>' + escapeHtml(strings.clearSelection || 'Clear Selection') + '</button>' +
                '</div>' +
            '</section>'
        );
    };

    const renderSeat = (seat, sectionIndex, rowIndex, seatIndex) => {
        const seatKey = keyForSeat(sectionIndex, rowIndex, seatIndex);
        const isSelected = state.selectedSeats.has(seatKey) ? ' checked' : '';

        return (
            '<div class="esc-designer-seat" data-seat-index="' + seatIndex + '">' +
                '<div class="esc-designer-seat__meta">' +
                    '<label class="esc-designer-seat__select">' +
                        '<input type="checkbox" data-action="toggle-seat"' + isSelected + ' />' +
                        '<span>' + escapeHtml(strings.selectSeat || 'Select') + '</span>' +
                    '</label>' +
                    '<span class="esc-designer-seat__label">' + escapeHtml(seat.seat_label || '') + '</span>' +
                '</div>' +
                '<div class="esc-designer-seat__fields">' +
                    '<label>' + escapeHtml(strings.seatType || 'Seat Type') +
                        '<select data-field="seat-type">' + renderSeatTypeOptions(seat.type) + '</select>' +
                    '</label>' +
                    '<label>' + escapeHtml(strings.seatGroup || 'Seat Group') +
                        '<select data-field="seat-group">' + renderGroupOptions(seat.group) + '</select>' +
                    '</label>' +
                '</div>' +
                '<button type="button" class="button button-link-delete" data-action="remove-seat">' + escapeHtml(strings.remove || 'Remove') + '</button>' +
            '</div>'
        );
    };

    const renderRow = (row, sectionIndex, rowIndex) => {
        const seatsHtml = (row.seats || []).map((seat, seatIndex) => renderSeat(seat, sectionIndex, rowIndex, seatIndex)).join('');
        const emptyState = (row.seats || []).length === 0
            ? '<p class="esc-designer-row__empty">' + escapeHtml(strings.walkwayHelp || 'Use row offsets or empty rows to create aisles.') + '</p>'
            : '';

        return (
            '<div class="esc-designer-row" data-row-index="' + rowIndex + '">' +
                '<div class="esc-designer-row__header">' +
                    '<label>' + escapeHtml(strings.rowLabel || 'Row Label') +
                        '<input type="text" data-field="row-label" value="' + escapeHtml(row.label || '') + '" />' +
                    '</label>' +
                    '<label>' + escapeHtml(strings.rowOffset || 'Row Offset') +
                        '<input type="number" min="0" step="1" data-field="row-offset" value="' + escapeHtml(row.offset || 0) + '" />' +
                    '</label>' +
                    '<div class="esc-designer-row__actions">' +
                        '<button type="button" class="button" data-action="add-seat">' + escapeHtml(strings.addSeat || 'Add Seat') + '</button>' +
                        '<button type="button" class="button button-link-delete" data-action="remove-row">' + escapeHtml(strings.remove || 'Remove') + '</button>' +
                    '</div>' +
                '</div>' +
                '<div class="esc-designer-row__seats">' + seatsHtml + emptyState + '</div>' +
            '</div>'
        );
    };

    const renderSection = (section, sectionIndex) => {
        const rowsHtml = (section.rows || []).map((row, rowIndex) => renderRow(row, sectionIndex, rowIndex)).join('');

        return (
            '<div class="esc-designer-section" data-section-index="' + sectionIndex + '">' +
                '<div class="esc-designer-section__header">' +
                    '<label>' + escapeHtml(strings.sectionName || 'Section Name') +
                        '<input type="text" data-field="section-name" value="' + escapeHtml(section.name || '') + '" />' +
                    '</label>' +
                    '<div class="esc-designer-section__actions">' +
                        '<button type="button" class="button" data-action="add-row">' + escapeHtml(strings.addRow || 'Add Row') + '</button>' +
                        '<button type="button" class="button button-link-delete" data-action="remove-section">' + escapeHtml(strings.remove || 'Remove') + '</button>' +
                    '</div>' +
                '</div>' +
                '<div class="esc-designer-section__rows">' + rowsHtml + '</div>' +
            '</div>'
        );
    };

    const render = () => {
        const sectionsHtml = state.sections.map((section, sectionIndex) => renderSection(section, sectionIndex)).join('');
        const emptyNotice = state.sections.length === 0
            ? '<p class="esc-designer-empty">' + escapeHtml(strings.emptyNotice || 'Add sections, rows, and seats to build your layout.') + '</p>'
            : '';

        const statusClass = state.statusType ? ' esc-designer-status--' + state.statusType : '';
        const statusMarkup = state.status
            ? '<p class="esc-designer-status' + statusClass + '">' + escapeHtml(state.status) + '</p>'
            : '';

        const saveLabel = state.isSaving
            ? escapeHtml(strings.saving || 'Saving…')
            : escapeHtml(strings.saveChanges || 'Save Seating Layout');

        app.innerHTML = (
            '<div class="esc-designer-toolbar">' +
                renderSeatTypesPanel() +
                renderGroupsPanel() +
                renderSettingsPanel() +
            '</div>' +
            renderSelectionTools() +
            '<div class="esc-designer-sections">' + sectionsHtml + emptyNotice + '</div>' +
            '<p><button type="button" class="button button-secondary" data-action="add-section">' + escapeHtml(strings.addSection || 'Add Section') + '</button></p>' +
            '<div class="esc-designer-actions">' +
                '<button type="button" class="button button-primary" data-action="save"' + (state.isSaving ? ' disabled' : '') + '>' + saveLabel + '</button>' +
                statusMarkup +
            '</div>'
        );
    };

    app.addEventListener('click', (event) => {
        const target = event.target;
        const action = target.getAttribute('data-action');

        if (!action) {
            return;
        }

        event.preventDefault();

        const sectionNode = target.closest('[data-section-index]');
        const rowNode = target.closest('[data-row-index]');
        const seatNode = target.closest('[data-seat-index]');

        const sectionIndex = sectionNode ? parseInt(sectionNode.getAttribute('data-section-index'), 10) : -1;
        const rowIndex = rowNode ? parseInt(rowNode.getAttribute('data-row-index'), 10) : -1;
        const seatIndex = seatNode ? parseInt(seatNode.getAttribute('data-seat-index'), 10) : -1;

        switch (action) {
        case 'add-section':
            addSection();
            break;
        case 'remove-section':
            if (sectionIndex >= 0) {
                removeSection(sectionIndex);
            }
            break;
        case 'add-row':
            if (sectionIndex >= 0) {
                addRow(sectionIndex);
            }
            break;
        case 'remove-row':
            if (sectionIndex >= 0 && rowIndex >= 0) {
                removeRow(sectionIndex, rowIndex);
            }
            break;
        case 'add-seat':
            if (sectionIndex >= 0 && rowIndex >= 0) {
                addSeat(sectionIndex, rowIndex);
            }
            break;
        case 'remove-seat':
            if (sectionIndex >= 0 && rowIndex >= 0 && seatIndex >= 0) {
                state.selectedSeats.delete(keyForSeat(sectionIndex, rowIndex, seatIndex));
                removeSeat(sectionIndex, rowIndex, seatIndex);
            }
            break;
        case 'save':
            handleSave();
            break;
        case 'add-seat-type':
            addSeatType();
            break;
        case 'remove-seat-type':
            {
                const typeNode = target.closest('[data-seat-type-id]');
                if (typeNode) {
                    const typeId = typeNode.getAttribute('data-seat-type-id');
                    removeSeatType(typeId);
                }
            }
            break;
        case 'add-seat-group':
            addGroup();
            break;
        case 'remove-seat-group':
            {
                const groupNode = target.closest('[data-seat-group-id]');
                if (groupNode) {
                    const groupId = groupNode.getAttribute('data-seat-group-id');
                    removeGroup(groupId);
                }
            }
            break;
        case 'apply-bulk-group':
            {
                const select = app.querySelector('[data-field="bulk-group"]');
                const groupId = select ? select.value : '';
                assignGroupToSelected(groupId);
            }
            break;
        case 'clear-bulk-group':
            assignGroupToSelected('');
            break;
        case 'clear-selection':
            clearSelection();
            render();
            break;
        case 'toggle-seat':
            if (sectionIndex >= 0 && rowIndex >= 0 && seatIndex >= 0) {
                const seatKey = keyForSeat(sectionIndex, rowIndex, seatIndex);
                if (state.selectedSeats.has(seatKey)) {
                    state.selectedSeats.delete(seatKey);
                } else {
                    state.selectedSeats.add(seatKey);
                }
                render();
            }
            break;
        default:
            break;
        }
    });

    app.addEventListener('input', (event) => {
        const target = event.target;
        const field = target.getAttribute('data-field');

        if (!field) {
            return;
        }

        const sectionNode = target.closest('[data-section-index]');
        const rowNode = target.closest('[data-row-index]');
        const seatNode = target.closest('[data-seat-index]');
        const typeNode = target.closest('[data-seat-type-id]');
        const groupNode = target.closest('[data-seat-group-id]');

        const sectionIndex = sectionNode ? parseInt(sectionNode.getAttribute('data-section-index'), 10) : -1;
        const rowIndex = rowNode ? parseInt(rowNode.getAttribute('data-row-index'), 10) : -1;
        const seatIndex = seatNode ? parseInt(seatNode.getAttribute('data-seat-index'), 10) : -1;

        if (typeNode && (field === 'seat-type-label' || field === 'seat-type-key')) {
            const typeId = typeNode.getAttribute('data-seat-type-id');
            const type = state.seatTypes.find((item) => item.id === typeId);
            if (!type) {
                return;
            }

            if (field === 'seat-type-label') {
                type.label = target.value;
            } else if (field === 'seat-type-key') {
                const newId = slugify(target.value);
                if (!newId || newId === type.id) {
                    target.value = type.id;
                    return;
                }

                if (seatTypeMap[newId]) {
                    target.value = type.id;
                    return;
                }

                const oldId = type.id;
                type.id = newId;
                state.sections.forEach((section) => {
                    (section.rows || []).forEach((row) => {
                        (row.seats || []).forEach((seat) => {
                            if (seat.type === oldId) {
                                seat.type = newId;
                            }
                        });
                    });
                });
                typeNode.setAttribute('data-seat-type-id', newId);
            }

            updateSeatTypeMap();
            render();
            return;
        }

        if (groupNode && (field === 'seat-group-name' || field === 'seat-group-prefix')) {
            const groupId = groupNode.getAttribute('data-seat-group-id');
            const group = state.groups.find((item) => item.id === groupId);
            if (!group) {
                return;
            }

            if (field === 'seat-group-name') {
                group.name = target.value;
            } else {
                group.prefix = target.value.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
            }

            renumberGroupSeats(groupId);
            render();
            return;
        }

        const section = sectionIndex >= 0 ? state.sections[sectionIndex] : null;
        if (!section) {
            if (field === 'seat-size') {
                state.settings.seat_size = target.value;
                return;
            }
            return;
        }

        if (field === 'section-name') {
            section.name = target.value;
            return;
        }

        const row = rowIndex >= 0 && section.rows ? section.rows[rowIndex] : null;
        if (!row) {
            return;
        }

        switch (field) {
        case 'row-label':
            row.label = target.value;
            break;
        case 'row-offset':
            row.offset = Math.max(0, parseInt(target.value, 10) || 0);
            target.value = row.offset;
            break;
        case 'seat-type':
            if (seatIndex >= 0 && row.seats && row.seats[seatIndex]) {
                row.seats[seatIndex].type = ensureSeatTypeExists(target.value);
            }
            break;
        case 'seat-group':
            if (seatIndex >= 0 && row.seats && row.seats[seatIndex]) {
                const seat = row.seats[seatIndex];
                const groupId = target.value;
                seat.group = groupId;
                if (groupId) {
                    renumberGroupSeats(groupId);
                } else {
                    resetSeatLabel(sectionIndex, rowIndex, seatIndex);
                }
                renumberAllGroups();
                render();
            }
            break;
        default:
            break;
        }
    });

    app.addEventListener('change', (event) => {
        const target = event.target;
        const field = target.getAttribute('data-field');

        if (field === 'bulk-group') {
            return;
        }

        if (field === 'seat-type' || field === 'seat-group' || field === 'row-offset') {
            // handled in input listener
            return;
        }

        if (field === 'seat-size') {
            state.settings.seat_size = target.value;
            render();
        }
    });

    render();
})();
