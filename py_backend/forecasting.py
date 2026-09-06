from flask import Flask, request, jsonify
from statsmodels.tsa.holtwinters import ExponentialSmoothing
import pandas as pd
# Stock-out prediction
# Demand prediction
app = Flask(__name__)

@app.route("/forecasting", methods=['POST'])
def thousandForecasts():
    data = request.get_json(force=True, silent=True)
    if data is None:
        return jsonify({"success": False, "error": "Invalid or missing JSON body"}), 400

    thousandDays = data # Fetch 1095 days data

    #if not isinstance(thousandDays, list) or len(thousandDays) < 14: 
        #return jsonify({"success": False, "error": "Need a list of at least 14 daily quantities"}), 400

    forecastData = pd.Series(thousandDays, dtype=float)

    # All-zero / flat history: Holt-Winters cannot fit, return flat zeros,  
    if (forecastData == 0).all():
        return jsonify({
            "success": True,
            "result": [0.0] * 30
        })


    model = ExponentialSmoothing(
        forecastData,
        trend='add',
        seasonal='add',
        seasonal_periods=7# Day of week, 7 days seasonal
    )

    fit_model = model.fit()
    forecasted_data = fit_model.forecast(steps=30) # 30 days forecast

    result = forecasted_data.tolist()


    return jsonify({
        "success": True,
        "result": result
    })

if __name__ == "__main__":
    app.run(debug=True,port=5000)