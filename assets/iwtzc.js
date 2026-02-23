(function () {
  const select = document.getElementById('iwtzc-timezone');
  const status = document.getElementById('iwtzc-status');
  const results = document.getElementById('iwtzc-results');

  if (!select || !status || !results || typeof iwtzcData === 'undefined') {
    return;
  }

  const setStatus = (message) => {
    status.textContent = message;
  };

  const setCell = (key, value) => {
    const cell = results.querySelector(`[data-key="${key}"]`);
    if (cell) {
      cell.textContent = value;
    }
  };

  select.addEventListener('change', async () => {
    const timezone = select.value;

    if (!timezone) {
      results.hidden = true;
      setStatus(iwtzcData.messages.choose);
      return;
    }

    setStatus('Loading...');

    try {
      const body = new URLSearchParams({
        action: 'iwtzc_lookup_timezone',
        nonce: iwtzcData.nonce,
        timezone,
      });

      const response = await fetch(iwtzcData.ajaxUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        },
        body,
      });

      const data = await response.json();

      if (!response.ok || !data.success) {
        throw new Error(data?.data?.message || iwtzcData.messages.error);
      }

      const details = data.data;

      setCell('iana_timezone', details.iana_timezone || '-');
      setCell('windows_timezone', details.windows_timezone || iwtzcData.messages.notMapped);
      setCell('utc_offset', details.utc_offset || '-');
      setCell('abbreviation', details.abbreviation || '-');
      setCell('is_dst', details.is_dst ? iwtzcData.messages.yes : iwtzcData.messages.no);
      setCell('current_local_time', details.current_local_time || '-');

      results.hidden = false;
      setStatus('');
    } catch (error) {
      results.hidden = true;
      setStatus(error.message || iwtzcData.messages.error);
    }
  });
})();
