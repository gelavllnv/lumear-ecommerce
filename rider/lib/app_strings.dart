import 'app_settings_controller.dart';

String tr({required String english, required String filipino}) {
  return appSettings.language == 'Filipino' ? filipino : english;
}
