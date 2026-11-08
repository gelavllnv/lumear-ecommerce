import 'package:flutter/material.dart';

import '../seller/product_media.dart';
import '../design/lumear_image.dart';

class AccountAvatar extends StatelessWidget {
  const AccountAvatar({
    super.key,
    this.photo,
    this.name = '',
    this.radius = 36,
  });
  final ProductMedia? photo;
  final String name;
  final double radius;
  @override
  Widget build(BuildContext context) {
    final fallback = Center(
      child: name.trim().isEmpty
          ? const Icon(Icons.person_outline, size: 30)
          : Text(
              name.trim()[0].toUpperCase(),
              style: TextStyle(fontSize: radius * .8),
            ),
    );
    return CircleAvatar(
      radius: radius,
      child: ClipOval(
        child: SizedBox(
          width: radius * 2,
          height: radius * 2,
          child: photo?.bytes != null
              ? Image.memory(
                  photo!.bytes!,
                  fit: BoxFit.cover,
                  errorBuilder: (_, _, _) => fallback,
                )
              : photo?.url != null
              ? LumearImage(
                  photo!.url!,
                  fit: BoxFit.cover,
                  errorBuilder: (_, _, _) => fallback,
                )
              : fallback,
        ),
      ),
    );
  }
}

ProductMedia? accountPhoto(dynamic value) {
  if (value is Map) {
    return ProductMedia.fromProduct({
      'media': [value],
    }).firstOrNull;
  }
  if (value is String && value.isNotEmpty) {
    return ProductMedia(name: 'Profile picture', isVideo: false, url: value);
  }
  return null;
}

class AccountPicturePicker extends StatefulWidget {
  const AccountPicturePicker({
    super.key,
    this.photo,
    required this.onChanged,
    this.onBusyChanged,
    this.enabled = true,
  });
  final ProductMedia? photo;
  final ValueChanged<ProductMedia?> onChanged;
  final ValueChanged<bool>? onBusyChanged;
  final bool enabled;
  @override
  State<AccountPicturePicker> createState() => _AccountPicturePickerState();
}

class _AccountPicturePickerState extends State<AccountPicturePicker> {
  bool picking = false;
  String? error;
  Future<void> pick() async {
    setState(() {
      picking = true;
      error = null;
    });
    widget.onBusyChanged?.call(true);
    try {
      final selected = await ProductMedia.pick(
        video: false,
        existing: const [],
      );
      if (mounted && selected != null) widget.onChanged(selected);
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) {
        setState(() => picking = false);
        widget.onBusyChanged?.call(false);
      }
    }
  }

  @override
  Widget build(BuildContext context) => Column(
    children: [
      AccountAvatar(photo: widget.photo, radius: 44),
      const SizedBox(height: 8),
      Wrap(
        alignment: WrapAlignment.center,
        spacing: 12,
        children: [
          TextButton.icon(
            onPressed: !widget.enabled || picking ? null : pick,
            icon: const Icon(Icons.add_a_photo_outlined),
            label: Text(
              widget.photo == null
                  ? 'Add profile picture'
                  : 'Change profile picture',
            ),
          ),
          if (widget.photo != null)
            TextButton(
              onPressed: !widget.enabled || picking
                  ? null
                  : () => widget.onChanged(null),
              child: const Text('Remove picture'),
            ),
        ],
      ),
      const Text('JPG, PNG or WEBP · up to 5 MB'),
      if (picking) const LinearProgressIndicator(),
      if (error != null) Text(error!),
    ],
  );
}
