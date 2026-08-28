import 'package:value_market_delivery_boy/core/constants/themeConstants.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

class DateTimeUtils {
static getFormattedDateTime(String datetime,
{bool isReturnOnlyDate = false}) {
if (datetime.trim().isNotEmpty && !datetime.contains(" ")) {
datetime = "$datetime 00:00:00";
}
DateTime? mainDatetime;
try {
mainDatetime = apiDateTimeFormat.parse(datetime);
} catch (e) {}

return mainDatetime == null
? datetime
: DateFormat(isReturnOnlyDate ? "MMM dd, yyyy" : "dd-MMM yyyy, HH:mm a")
.format(mainDatetime);
}

static String fromTime(
{required TimeOfDay timeOfDay, required BuildContext context}) {
return timeOfDay.format(context);
}

static int getHourFromTimeDetails({required String time}) {
final timeDetails = time.split(":");
return int.parse(timeDetails[0]);
}

static int getMinuteFromTimeDetails({required String time}) {
final timeDetails = time.split(":");
return int.parse(timeDetails[1]);
}
}
