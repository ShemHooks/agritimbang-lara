"""ARIMA process: read JSON from stdin, write JSON to stdout; diagnostics to stderr."""
import json
import math
import sys
import warnings

import numpy as np
from statsmodels.tsa.arima.model import ARIMA


def main():
    payload = json.load(sys.stdin)
    prices = payload['prices']
    weeks = payload['weeks']
    if not isinstance(prices, list) or len(prices) < 12:
        raise ValueError('At least 12 observations required')
    if not isinstance(weeks, int) or not 1 <= weeks <= 12:
        raise ValueError('weeks must be between 1 and 12')
    series = np.asarray(prices, dtype=float)
    if not np.all(np.isfinite(series)) or np.any(series <= 0):
        raise ValueError('prices must be finite and positive')

    order = (1, 1, 0)
    with warnings.catch_warnings():
        warnings.simplefilter('ignore')
        fitted = ARIMA(series, order=order, enforce_stationarity=False,
                       enforce_invertibility=False).fit()
        prediction = fitted.get_forecast(steps=weeks)
    means = prediction.predicted_mean
    bounds = prediction.conf_int(alpha=0.05)
    points = []
    for mean, interval in zip(means, bounds):
        lower, upper = float(interval[0]), float(interval[1])
        mean = float(mean)
        if not all(math.isfinite(x) for x in (mean, lower, upper)):
            raise ValueError('model returned non-finite values')
        # Prices cannot be negative; clamp forecast and interval to zero.
        points.append({
            'predicted_price': max(0.0, mean),
            'lower_95': max(0.0, lower),
            'upper_95': max(0.0, upper),
        })
    print(json.dumps({'order': list(order), 'predictions': points}, allow_nan=False))


if __name__ == '__main__':
    try:
        main()
    except Exception as exc:
        print(f'{type(exc).__name__}: {exc}', file=sys.stderr)
        sys.exit(1)
