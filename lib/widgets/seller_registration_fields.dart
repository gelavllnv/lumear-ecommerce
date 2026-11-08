import 'dart:convert';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';

class SellerRegistrationFields extends StatefulWidget {
  const SellerRegistrationFields({
    super.key,
    required this.onChanged,
    required this.onBusyChanged,
  });

  final ValueChanged<Map<String, Map<String, String>>> onChanged;
  final ValueChanged<bool> onBusyChanged;

  @override
  State<SellerRegistrationFields> createState() =>
      _SellerRegistrationFieldsState();
}

class _SellerRegistrationFieldsState extends State<SellerRegistrationFields> {
  final documents = <String, Map<String, String>>{};
  bool busy = false;
  String? error;

  Future<void> pick(String type, FormFieldState<String> field) async {
    if (busy) return;
    setState(() {
      busy = true;
      error = null;
    });
    widget.onBusyChanged(true);
    try {
      final files = await FilePicker.pickFiles(
        type: FileType.custom,
        allowedExtensions: ['jpg', 'jpeg', 'png', 'pdf'],
      );
      if (files.isEmpty || !mounted) return;
      final file = files.first;
      final bytes = await file.readAsBytes();
      if (!mounted) return;
      if (bytes.isEmpty || bytes.length > 5 * 1024 * 1024) {
        setState(
          () => error = 'Choose a non-empty JPG, PNG, or PDF up to 5 MB.',
        );
        return;
      }
      documents[type] = {'name': file.name, 'data': base64Encode(bytes)};
      field.didChange(file.name);
      widget.onChanged(Map.from(documents));
    } catch (_) {
      if (mounted) {
        setState(() => error = 'Unable to select the file. Please try again.');
      }
    } finally {
      if (mounted) {
        setState(() => busy = false);
        widget.onBusyChanged(false);
      }
    }
  }

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      const Text('Government-issued ID'),
      const SizedBox(height: 8),
      const Text(
        'Upload your valid government-issued ID as JPG, PNG, or PDF, up to 5 MB. Your ID is stored privately for seller approval.',
      ),
      for (final entry in const {'id': 'Government-issued ID'}.entries)
        Padding(
          padding: const EdgeInsets.symmetric(vertical: 8),
          child: FormField<String>(
            validator: (value) => value == null
                ? 'Select your ${entry.value.toLowerCase()}.'
                : null,
            builder: (state) => InputDecorator(
              decoration: InputDecoration(
                labelText: entry.value,
                errorText: state.errorText,
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(state.value ?? 'No file selected'),
                  TextButton.icon(
                    onPressed: busy ? null : () => pick(entry.key, state),
                    icon: const Icon(Icons.upload_file_outlined),
                    label: Text(
                      state.value == null ? 'Select file' : 'Replace file',
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      if (error != null)
        Text(
          error!,
          style: TextStyle(color: Theme.of(context).colorScheme.error),
        ),
    ],
  );
}
