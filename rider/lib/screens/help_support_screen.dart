import 'dart:convert';
import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';

import 'bug_report_screen.dart';

import '../ai/lumear_ai_knowledge.dart';

class HelpSupportScreen extends StatefulWidget {
  const HelpSupportScreen({super.key});

  @override
  State<HelpSupportScreen> createState() => _HelpSupportScreenState();
}

class _HelpSupportScreenState extends State<HelpSupportScreen> {
  // ============================================================
  // COLORS
  // ============================================================

  static const Color darkMaroon = Color(0xFF62191C);

  static const Color maroon = Color(0xFF873632);

  static const Color brownBeige = Color(0xFF9E7161);

  static const Color lightBeige = Color(0xFFCAAE9F);

  static const Color cream = Color(0xFFE0CFC2);

  static const Color chatBackground = Color(0xFFF8F5F3);

  static const Color inputBackground = Color(0xFFF7F3F1);

  static const Color borderColor = Color(0xFFEAE0DC);

  static const Color darkText = Color(0xFF3F2927);

  static const Color mutedText = Color(0xFFAE9790);

  // ============================================================
  // OPENROUTER
  // ============================================================

  // Paste your OpenRouter key locally for testing.
  // Do NOT commit your real key to GitHub.
  // For production, move this request to your Laravel backend.
  static const String _openRouterApiKey = '';

  // Free router: automatically selects an available free model.
  static const String _openRouterModel = 'openrouter/free';

  static const String _openRouterUrl =
      'https://openrouter.ai/api/v1/chat/completions';

  // ============================================================
  // CONTROLLERS
  // ============================================================

  final TextEditingController _messageController = TextEditingController();

  final ScrollController _scrollController = ScrollController();

  final ImagePicker _imagePicker = ImagePicker();

  final http.Client _httpClient = http.Client();

  // ============================================================
  // STATE
  // ============================================================

  bool _isBotTyping = false;

  String _currentMenu = 'main';

  // Pending attachment means:
  // selected but NOT sent yet.
  String? _pendingAttachmentPath;
  String? _pendingAttachmentType;
  String? _pendingAttachmentName;
  String? _pendingMimeType;

  final List<Map<String, dynamic>> _messages = [];

  // ============================================================
  // INIT
  // ============================================================

  @override
  void initState() {
    super.initState();

    _messages.add({
      'message':
          'Hello, Rider! 👋\n\n'
          'Welcome to Lumear AI Support. '
          'How can I help you today?',
      'isRider': false,
      'time': DateTime.now(),
      'sendToGemini': false,
      'attachmentPath': null,
      'attachmentType': null,
      'fileName': null,
    });
  }

  // ============================================================
  // DISPOSE
  // ============================================================

  @override
  void dispose() {
    _messageController.dispose();
    _scrollController.dispose();
    _httpClient.close();

    super.dispose();
  }

  // ============================================================
  // SEND
  // ============================================================

  Future<void> _sendMessage() async {
    if (_isBotTyping) {
      return;
    }

    final String text = _messageController.text.trim();

    final bool hasAttachment = _pendingAttachmentPath != null;

    if (text.isEmpty && !hasAttachment) {
      return;
    }

    FocusScope.of(context).unfocus();

    final String? attachmentPath = _pendingAttachmentPath;

    final String? attachmentType = _pendingAttachmentType;

    final String? attachmentName = _pendingAttachmentName;

    final String? mimeType = _pendingMimeType;

    _messageController.clear();

    // Remove preview after Send is pressed.
    setState(() {
      _pendingAttachmentPath = null;
      _pendingAttachmentType = null;
      _pendingAttachmentName = null;
      _pendingMimeType = null;
    });

    await _sendToOpenRouter(
      riderMessage: text,
      attachmentPath: attachmentPath,
      attachmentType: attachmentType,
      attachmentName: attachmentName,
      mimeType: mimeType,
    );
  }

  // ============================================================
  // ADD RIDER MESSAGE
  // ============================================================

  void _addRiderMessage(
    String message, {
    bool sendToGemini = true,
    String? attachmentPath,
    String? attachmentType,
    String? fileName,
  }) {
    if (!mounted) {
      return;
    }

    setState(() {
      _messages.add({
        'message': message,
        'isRider': true,
        'time': DateTime.now(),
        'sendToGemini': sendToGemini,
        'attachmentPath': attachmentPath,
        'attachmentType': attachmentType,
        'fileName': fileName,
      });
    });

    _scrollToBottom();
  }

  // ============================================================
  // LOCAL AI MESSAGE
  // ============================================================

  Future<void> _addLocalBotMessage(String message) async {
    if (!mounted) {
      return;
    }

    setState(() {
      _isBotTyping = true;
    });

    _scrollToBottom();

    await Future.delayed(const Duration(milliseconds: 350));

    if (!mounted) {
      return;
    }

    setState(() {
      _messages.add({
        'message': message,
        'isRider': false,
        'time': DateTime.now(),
        'sendToGemini': false,
        'attachmentPath': null,
        'attachmentType': null,
        'fileName': null,
      });

      _isBotTyping = false;
    });

    _scrollToBottom();
  }

  // ============================================================
  // OPENROUTER
  // ============================================================

  Future<void> _sendToOpenRouter({
    required String riderMessage,
    String? attachmentPath,
    String? attachmentType,
    String? attachmentName,
    String? mimeType,
  }) async {
    if (_openRouterApiKey.trim().isEmpty ||
        _openRouterApiKey == 'YOUR_OPENROUTER_API_KEY_HERE') {
      _showSnackBar('Add your OpenRouter API key first.');
      return;
    }

    final String displayMessage = riderMessage.isNotEmpty
        ? riderMessage
        : _defaultAttachmentPrompt(attachmentType);

    _addRiderMessage(
      displayMessage,
      sendToGemini: true,
      attachmentPath: attachmentPath,
      attachmentType: attachmentType,
      fileName: attachmentName,
    );

    if (!mounted) {
      return;
    }

    setState(() {
      _isBotTyping = true;
    });

    _scrollToBottom();

    try {
      final List<Map<String, dynamic>> messages = [
        {
          'role': 'system',
          'content':
              '''
${LumearAiKnowledge.systemInstruction}

You are Lumear AI Support for the Lumear Rider App.

Rules:
- Respond specifically to the rider's latest request.
- Keep answers concise, practical, and easy to follow.
- If the rider reports a possible app bug, suggest simple safe troubleshooting first.
- If the issue still appears to be a possible app bug, tell the rider they can use the Bug Report feature to submit evidence for admin review.
- Do not promise a guaranteed fix or an immediate admin response.
- Do not invent Lumear features, account data, delivery data, or policies.
- If an attachment is provided, actually examine it.
- Do not invent text or details that are unreadable.
''',
        },
      ];

      // ========================================================
      // RECENT CHAT HISTORY
      // ========================================================

      final List<Map<String, dynamic>> history = _messages
          .where(
            (message) =>
                message['sendToGemini'] == true &&
                message['attachmentPath'] == null,
          )
          .toList();

      // Current rider message was already inserted into _messages above.
      // Remove it from history so it is not sent twice.
      if (history.isNotEmpty &&
          history.last['isRider'] == true &&
          history.last['message']?.toString() == displayMessage) {
        history.removeLast();
      }

      final int startIndex = history.length > 6 ? history.length - 6 : 0;

      final List<Map<String, dynamic>> recentHistory = history.sublist(
        startIndex,
      );

      for (final Map<String, dynamic> message in recentHistory) {
        messages.add({
          'role': message['isRider'] == true ? 'user' : 'assistant',
          'content': message['message']?.toString() ?? '',
        });
      }

      // ========================================================
      // CURRENT USER REQUEST + ATTACHMENT
      // ========================================================

      dynamic currentContent = displayMessage;

      if (attachmentPath != null) {
        final File file = File(attachmentPath);

        if (!await file.exists()) {
          throw Exception('Attachment does not exist.');
        }

        final int fileSize = await file.length();

        const int maxBytes = 20 * 1024 * 1024;

        if (fileSize > maxBytes) {
          throw Exception('The selected file is larger than 20 MB.');
        }

        final List<Map<String, dynamic>> contentParts = [];

        // Text prompt FIRST.
        contentParts.add({'type': 'text', 'text': displayMessage});

        // IMAGE / SCREENSHOT
        if (attachmentType == 'image') {
          final List<int> bytes = await file.readAsBytes();
          final String base64Data = base64Encode(bytes);
          final String imageMime = mimeType ?? 'image/jpeg';

          contentParts.add({
            'type': 'image_url',
            'image_url': {'url': 'data:$imageMime;base64,$base64Data'},
          });
        }
        // PDF
        else if (attachmentType == 'pdf') {
          final List<int> bytes = await file.readAsBytes();
          final String base64Data = base64Encode(bytes);

          contentParts.add({
            'type': 'file',
            'file': {
              'filename': attachmentName ?? 'document.pdf',
              'file_data': 'data:application/pdf;base64,$base64Data',
            },
          });
        }
        // TXT
        else if (attachmentType == 'text') {
          final String documentText = await file.readAsString();

          contentParts.add({
            'type': 'text',
            'text':
                'Attached text document (${attachmentName ?? 'document.txt'}):\n'
                '$documentText',
          });
        }

        currentContent = contentParts;
      }

      messages.add({'role': 'user', 'content': currentContent});

      final Map<String, dynamic> requestBody = {
        'model': _openRouterModel,
        'messages': messages,
        'temperature': 0.3,
        'max_tokens': 500,
      };

      // Free PDF parsing through OpenRouter.
      if (attachmentType == 'pdf') {
        requestBody['plugins'] = [
          {
            'id': 'file-parser',
            'pdf': {'engine': 'cloudflare-ai'},
          },
        ];
      }

      final http.Response response = await _httpClient
          .post(
            Uri.parse(_openRouterUrl),
            headers: {
              'Content-Type': 'application/json',
              'Authorization': 'Bearer $_openRouterApiKey',
              'X-Title': 'Lumear Rider App',
            },
            body: jsonEncode(requestBody),
          )
          .timeout(const Duration(seconds: 45));

      if (!mounted) {
        return;
      }

      debugPrint('========== OPENROUTER ==========');
      debugPrint('STATUS: ${response.statusCode}');
      debugPrint('BODY: ${response.body}');
      debugPrint('================================');

      if (response.statusCode >= 200 && response.statusCode < 300) {
        final Map<String, dynamic> responseData =
            jsonDecode(response.body) as Map<String, dynamic>;

        final String aiText = _extractOpenRouterText(responseData).trim();

        setState(() {
          _messages.add({
            'message': aiText.isEmpty
                ? 'This concern may need further assistance from Lumear Admin Support.'
                : aiText,
            'isRider': false,
            'time': DateTime.now(),
            'sendToGemini': true,
            'attachmentPath': null,
            'attachmentType': null,
            'fileName': null,
          });

          _isBotTyping = false;
          _currentMenu = 'answer';
        });

        _scrollToBottom();
        return;
      }

      String errorMessage =
          'Lumear AI is temporarily unavailable. Please try again.';

      if (response.statusCode == 400) {
        errorMessage =
            'Lumear AI could not process that request. Please try again.';
      } else if (response.statusCode == 401) {
        errorMessage = 'The OpenRouter API key could not be authenticated.';
      } else if (response.statusCode == 402) {
        errorMessage = 'This request needs credits or a different free model.';
      } else if (response.statusCode == 403) {
        errorMessage = 'OpenRouter blocked this request because of account or model permissions.';
      } else if (response.statusCode == 404) {
        errorMessage =
            'The selected OpenRouter model or endpoint could not be found.';
      } else if (response.statusCode == 408) {
        errorMessage = 'Lumear AI took too long to respond. Please try again.';
      } else if (response.statusCode == 429) {
        errorMessage = 'The free AI request limit has been reached. Please try again later.';
      } else if (response.statusCode >= 500) {
        errorMessage = 'The AI provider is temporarily unavailable. Please try again shortly.';
      }

      _addApiErrorMessage(errorMessage);
    } catch (error, stackTrace) {
      debugPrint('OpenRouter error: $error');
      debugPrint(stackTrace.toString());

      if (!mounted) {
        return;
      }

      _addApiErrorMessage(
        error.toString().contains('larger than 20 MB')
            ? 'The selected attachment is too large. '
                  'Please choose a file smaller than 20 MB.'
            : 'Unable to connect to Lumear AI. '
                  'Please check your internet connection and try again.',
      );
    }
  }

  // ============================================================
  // DEFAULT ATTACHMENT PROMPT
  // ============================================================

  String _defaultAttachmentPrompt(String? type) {
    if (type == 'image') {
      return 'Please analyze this image and '
          'help me based on the Lumear Rider App.';
    }

    if (type == 'pdf') {
      return 'Please read this PDF and help me '
          'with any relevant delivery information.';
    }

    if (type == 'text') {
      return 'Please read this document and help '
          'me understand the relevant information.';
    }

    return 'Please analyze this attachment.';
  }

  // ============================================================
  // EXTRACT OPENROUTER RESPONSE
  // ============================================================

  String _extractOpenRouterText(Map<String, dynamic> data) {
    try {
      final dynamic choicesRaw = data['choices'];

      if (choicesRaw is! List || choicesRaw.isEmpty) {
        debugPrint('OpenRouter: choices missing.');
        return 'Lumear AI returned no answer. Please try again.';
      }

      final dynamic firstChoice = choicesRaw.first;

      if (firstChoice is! Map) {
        debugPrint('OpenRouter: invalid first choice.');
        return 'I could not read the AI response.';
      }

      final dynamic message = firstChoice['message'];

      if (message is Map) {
        final dynamic content = message['content'];

        // Normal OpenRouter response.
        if (content is String) {
          final String answer = content.trim();

          if (answer.isNotEmpty) {
            return answer;
          }
        }

        // Some multimodal providers may return content parts.
        if (content is List) {
          final StringBuffer buffer = StringBuffer();

          for (final dynamic part in content) {
            if (part is String) {
              buffer.write(part);
              continue;
            }

            if (part is Map) {
              final dynamic text = part['text'];

              if (text != null) {
                buffer.write(text.toString());
              }

              final dynamic partContent = part['content'];

              if (partContent is String) {
                buffer.write(partContent);
              }
            }
          }

          final String answer = buffer.toString().trim();

          if (answer.isNotEmpty) {
            return answer;
          }
        }

        // Some providers may place useful text in reasoning.
        final dynamic reasoning = message['reasoning'];

        if (reasoning is String && reasoning.trim().isNotEmpty) {
          return reasoning.trim();
        }
      }

      // Legacy-compatible fallback.
      final dynamic choiceText = firstChoice['text'];

      if (choiceText is String && choiceText.trim().isNotEmpty) {
        return choiceText.trim();
      }

      debugPrint('OpenRouter response could not be extracted: $firstChoice');

      return 'Lumear AI returned an empty response. Please try again.';
    } catch (error, stackTrace) {
      debugPrint('OpenRouter parsing error: $error');
      debugPrint(stackTrace.toString());

      return 'I had trouble reading the AI response. Please try again.';
    }
  }

  // ============================================================
  // API ERROR
  // ============================================================

  void _addApiErrorMessage(String message) {
    if (!mounted) {
      return;
    }

    setState(() {
      _messages.add({
        'message': message,
        'isRider': false,
        'time': DateTime.now(),
        'sendToGemini': false,
        'attachmentPath': null,
        'attachmentType': null,
        'fileName': null,
      });

      _isBotTyping = false;
      _currentMenu = 'error';
    });

    _scrollToBottom();
  }

  // ============================================================
  // QUICK REPLIES
  // ============================================================

  List<String> _getQuickReplies() {
    switch (_currentMenu) {
      case 'main':
        return [
          '📦 Delivery Problem',
          '📱 App Problem',
          '📷 QR Scanner',
          '👤 Account Problem',
          '💰 Earnings / Payment',
          '📍 Pickup / Location',
          '🐞 Report a Bug',
        ];

      case 'delivery':
        return [
          'Customer unavailable',
          'Wrong delivery address',
          'Parcel appears damaged',
          'Wrong parcel',
          'Cannot complete delivery',
          '🏠 Back to Main Menu',
        ];

      case 'app':
        return [
          'Start Delivery is missing',
          'Delivery status is not updating',
          'Proof of delivery is not uploading',
          'App is not responding',
          '🏠 Back to Main Menu',
        ];

      case 'qr':
        return [
          'QR code is not scanning',
          'Camera is not opening',
          'Wrong camera is opening',
          'QR verification failed',
          '🏠 Back to Main Menu',
        ];

      case 'account':
        return [
          'Cannot login',
          'Forgot password',
          'Account verification',
          'Update rider profile',
          '🏠 Back to Main Menu',
        ];

      case 'payment':
        return [
          'Missing earnings',
          'Incorrect earnings',
          'Completed delivery not reflected',
          '🏠 Back to Main Menu',
        ];

      case 'location':
        return [
          'Cannot find pickup location',
          'GPS / Map problem',
          'Cannot find customer address',
          '🏠 Back to Main Menu',
        ];

      default:
        return ['🐞 Report a Bug', '🏠 Back to Main Menu'];
    }
  }

  Future<void> _selectQuickReply(String option) async {
    if (_isBotTyping) {
      return;
    }

    if (option == '📦 Delivery Problem') {
      _addRiderMessage(option, sendToGemini: false);

      setState(() {
        _currentMenu = 'delivery';
      });

      await _addLocalBotMessage('What delivery problem are you experiencing?');

      return;
    }

    if (option == '📱 App Problem') {
      _addRiderMessage(option, sendToGemini: false);

      setState(() {
        _currentMenu = 'app';
      });

      await _addLocalBotMessage(
        'What part of the Lumear Rider App '
        'is giving you a problem?',
      );

      return;
    }

    if (option == '📷 QR Scanner') {
      _addRiderMessage(option, sendToGemini: false);

      setState(() {
        _currentMenu = 'qr';
      });

      await _addLocalBotMessage(
        'What problem are you experiencing '
        'with the QR scanner?',
      );

      return;
    }

    if (option == '👤 Account Problem') {
      _addRiderMessage(option, sendToGemini: false);

      setState(() {
        _currentMenu = 'account';
      });

      await _addLocalBotMessage('What account problem are you experiencing?');

      return;
    }

    if (option == '💰 Earnings / Payment') {
      _addRiderMessage(option, sendToGemini: false);

      setState(() {
        _currentMenu = 'payment';
      });

      await _addLocalBotMessage(
        'What earnings or payment concern '
        'are you experiencing?',
      );

      return;
    }

    if (option == '📍 Pickup / Location') {
      _addRiderMessage(option, sendToGemini: false);

      setState(() {
        _currentMenu = 'location';
      });

      await _addLocalBotMessage(
        'What pickup or location concern '
        'are you experiencing?',
      );

      return;
    }
    if (option == '🐞 Report a Bug') {
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) =>
              const BugReportScreen(affectedScreen: 'Help & Support'),
        ),
      );

      return;
    }

    if (option == '🏠 Back to Main Menu') {
      _addRiderMessage(option, sendToGemini: false);

      setState(() {
        _currentMenu = 'main';
      });

      await _addLocalBotMessage('Sure! What else can I help you with?');

      return;
    }

    if (option == '🐞 Report a Bug') {
      _addRiderMessage(option, sendToGemini: false);

      await _addLocalBotMessage(
        'Bug reporting will open a form where your rider information, date, and time are added automatically. You will only need to provide screenshots or photos and describe what happened.',
      );

      return;
    }

    await _sendToOpenRouter(riderMessage: option);
  }

  // ============================================================
  // ATTACHMENT MENU
  // ============================================================

  void _showAttachmentMenu() {
    if (_isBotTyping) {
      return;
    }

    FocusScope.of(context).unfocus();

    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (BuildContext sheetContext) {
        return SafeArea(
          child: Container(
            margin: const EdgeInsets.all(12),
            padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(28),
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 40,
                  height: 4,
                  decoration: BoxDecoration(
                    color: Colors.grey.shade300,
                    borderRadius: BorderRadius.circular(10),
                  ),
                ),

                const SizedBox(height: 20),

                const Align(
                  alignment: Alignment.centerLeft,
                  child: Text(
                    'Send attachment',
                    style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w700,
                      color: darkMaroon,
                    ),
                  ),
                ),

                const SizedBox(height: 18),

                Row(
                  children: [
                    Expanded(
                      child: _attachmentOption(
                        icon: Icons.camera_alt_rounded,
                        label: 'Camera',
                        onTap: () {
                          Navigator.pop(sheetContext);

                          _pickImage(ImageSource.camera);
                        },
                      ),
                    ),

                    Expanded(
                      child: _attachmentOption(
                        icon: Icons.photo_library_rounded,
                        label: 'Gallery',
                        onTap: () {
                          Navigator.pop(sheetContext);

                          _pickImage(ImageSource.gallery);
                        },
                      ),
                    ),

                    Expanded(
                      child: _attachmentOption(
                        icon: Icons.description_rounded,
                        label: 'File',
                        onTap: () {
                          Navigator.pop(sheetContext);

                          _pickDocument();
                        },
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _attachmentOption({
    required IconData icon,
    required String label,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: Padding(
        padding: const EdgeInsets.all(7),
        child: Column(
          children: [
            Container(
              width: 58,
              height: 58,
              decoration: BoxDecoration(
                color: cream,
                borderRadius: BorderRadius.circular(18),
              ),
              child: Icon(icon, color: darkMaroon, size: 27),
            ),

            const SizedBox(height: 8),

            Text(
              label,
              style: const TextStyle(
                color: darkMaroon,
                fontWeight: FontWeight.w600,
                fontSize: 12,
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ============================================================
  // PICK IMAGE
  // ============================================================

  Future<void> _pickImage(ImageSource source) async {
    try {
      final XFile? image = await _imagePicker.pickImage(
        source: source,
        imageQuality: 80,
        maxWidth: 1600,
      );

      if (image == null || !mounted) {
        return;
      }

      final String extension = _extensionOf(image.name);

      setState(() {
        _pendingAttachmentPath = image.path;

        _pendingAttachmentType = 'image';

        _pendingAttachmentName = image.name;

        _pendingMimeType = extension == 'png' ? 'image/png' : 'image/jpeg';
      });
    } catch (error) {
      debugPrint('Image picker: $error');

      _showSnackBar('Unable to open the camera or gallery.');
    }
  }

  // ============================================================
  // PICK FILE - FILE_PICKER V12
  // ============================================================

  Future<void> _pickDocument() async {
    try {
      final PlatformFile? file = await FilePicker.pickFile(
        type: FileType.custom,
        allowedExtensions: ['pdf', 'txt'],
      );

      if (file == null || !mounted) {
        return;
      }

      if (file.path == null) {
        _showSnackBar('Unable to access the selected file.');

        return;
      }

      final String extension = _extensionOf(file.name);

      String mimeType;
      String attachmentType;

      if (extension == 'pdf') {
        mimeType = 'application/pdf';
        attachmentType = 'pdf';
      } else {
        mimeType = 'text/plain';
        attachmentType = 'text';
      }

      setState(() {
        _pendingAttachmentPath = file.path;

        _pendingAttachmentType = attachmentType;

        _pendingAttachmentName = file.name;

        _pendingMimeType = mimeType;
      });
    } catch (error) {
      debugPrint('File picker: $error');

      _showSnackBar('Unable to open the file picker.');
    }
  }

  String _extensionOf(String fileName) {
    final int dot = fileName.lastIndexOf('.');

    if (dot < 0 || dot == fileName.length - 1) {
      return '';
    }

    return fileName.substring(dot + 1).toLowerCase();
  }

  // ============================================================
  // REMOVE ATTACHMENT
  // ============================================================

  void _removePendingAttachment() {
    setState(() {
      _pendingAttachmentPath = null;
      _pendingAttachmentType = null;
      _pendingAttachmentName = null;
      _pendingMimeType = null;
    });
  }

  // ============================================================
  // RESET
  // ============================================================

  void _resetConversation() {
    if (_isBotTyping) {
      return;
    }

    setState(() {
      _messages.clear();

      _currentMenu = 'main';

      _pendingAttachmentPath = null;
      _pendingAttachmentType = null;
      _pendingAttachmentName = null;
      _pendingMimeType = null;

      _messages.add({
        'message':
            'Hello, Rider! 👋\n\n'
            'Welcome to Lumear AI Support. '
            'How can I help you today?',
        'isRider': false,
        'time': DateTime.now(),
        'sendToGemini': false,
        'attachmentPath': null,
        'attachmentType': null,
        'fileName': null,
      });
    });

    _scrollToBottom();
  }

  // ============================================================
  // SCROLL
  // ============================================================

  void _scrollToBottom() {
    Future.delayed(const Duration(milliseconds: 100), () {
      if (!_scrollController.hasClients) {
        return;
      }

      _scrollController.animateTo(
        _scrollController.position.maxScrollExtent,
        duration: const Duration(milliseconds: 280),
        curve: Curves.easeOutCubic,
      );
    });
  }

  // ============================================================
  // TIME
  // ============================================================

  String _formatTime(DateTime dateTime) {
    int hour = dateTime.hour;

    final String minute = dateTime.minute.toString().padLeft(2, '0');

    final String period = hour >= 12 ? 'PM' : 'AM';

    if (hour == 0) {
      hour = 12;
    } else if (hour > 12) {
      hour -= 12;
    }

    return '$hour:$minute $period';
  }

  void _showSnackBar(String text) {
    if (!mounted) {
      return;
    }

    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    final List<String> quickReplies = _getQuickReplies();

    return Scaffold(
      backgroundColor: chatBackground,

      appBar: AppBar(
        backgroundColor: darkMaroon,
        foregroundColor: Colors.white,
        elevation: 0,
        titleSpacing: 12,

        title: const Row(
          children: [
            CircleAvatar(
              radius: 20,
              backgroundColor: lightBeige,
              child: Icon(Icons.support_agent_rounded, color: darkMaroon),
            ),

            SizedBox(width: 11),

            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Lumear AI Support',
                  style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
                ),

                SizedBox(height: 2),

                Text(
                  'Online • AI Assistant',
                  style: TextStyle(fontSize: 10.5, color: lightBeige),
                ),
              ],
            ),
          ],
        ),

        actions: [
          IconButton(
            tooltip: 'Restart conversation',
            onPressed: _resetConversation,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),

      body: Column(
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 9),
            color: const Color(0xFFF0E6E1),
            child: const Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(Icons.info_outline_rounded, size: 17, color: maroon),

                SizedBox(width: 7),

                Expanded(
                  child: Text(
                    'Lumear AI can help with app usage, '
                    'deliveries, screenshots and supported '
                    'documents.',
                    style: TextStyle(fontSize: 11, height: 1.4, color: maroon),
                  ),
                ),
              ],
            ),
          ),

          // ====================================================
          // MESSAGES
          // ====================================================
          Expanded(
            child: ListView.builder(
              controller: _scrollController,
              padding: const EdgeInsets.fromLTRB(14, 18, 14, 12),
              itemCount: _messages.length + (_isBotTyping ? 1 : 0),
              itemBuilder: (context, index) {
                if (_isBotTyping && index == _messages.length) {
                  return _typingBubble();
                }

                final message = _messages[index];

                return _messageBubble(
                  message: message['message']?.toString() ?? '',
                  isRider: message['isRider'] == true,
                  time: message['time'] as DateTime,
                  attachmentPath: message['attachmentPath']?.toString(),
                  attachmentType: message['attachmentType']?.toString(),
                  fileName: message['fileName']?.toString(),
                );
              },
            ),
          ),

          // ====================================================
          // QUICK REPLIES
          // ====================================================
          if (!_isBotTyping && quickReplies.isNotEmpty)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(vertical: 7),
              color: Colors.white,
              child: SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 10),
                child: Row(
                  children: quickReplies.map((reply) {
                    return Padding(
                      padding: const EdgeInsets.only(right: 7),
                      child: OutlinedButton(
                        onPressed: () => _selectQuickReply(reply),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: darkMaroon,
                          side: const BorderSide(color: borderColor),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(20),
                          ),
                        ),
                        child: Text(
                          reply,
                          style: const TextStyle(fontSize: 11.5),
                        ),
                      ),
                    );
                  }).toList(),
                ),
              ),
            ),

          // ====================================================
          // PENDING ATTACHMENT PREVIEW
          // ====================================================
          if (_pendingAttachmentPath != null) _pendingAttachmentPreview(),

          // ====================================================
          // INPUT
          // ====================================================
          Container(
            padding: const EdgeInsets.fromLTRB(8, 8, 10, 10),
            decoration: const BoxDecoration(
              color: Colors.white,
              border: Border(top: BorderSide(color: borderColor)),
            ),
            child: SafeArea(
              top: false,
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  IconButton(
                    onPressed: _isBotTyping ? null : _showAttachmentMenu,
                    icon: const Icon(Icons.add_circle_outline_rounded),
                    color: darkMaroon,
                    iconSize: 28,
                  ),

                  Expanded(
                    child: Container(
                      constraints: const BoxConstraints(
                        minHeight: 46,
                        maxHeight: 115,
                      ),
                      decoration: BoxDecoration(
                        color: inputBackground,
                        borderRadius: BorderRadius.circular(24),
                        border: Border.all(color: borderColor),
                      ),
                      child: TextField(
                        controller: _messageController,
                        enabled: !_isBotTyping,
                        minLines: 1,
                        maxLines: 4,
                        textCapitalization: TextCapitalization.sentences,
                        decoration: const InputDecoration(
                          hintText: 'Message Lumear Support',
                          border: InputBorder.none,
                          contentPadding: EdgeInsets.symmetric(
                            horizontal: 16,
                            vertical: 13,
                          ),
                        ),
                      ),
                    ),
                  ),

                  const SizedBox(width: 7),

                  SizedBox(
                    width: 46,
                    height: 46,
                    child: ElevatedButton(
                      onPressed: _isBotTyping ? null : _sendMessage,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: darkMaroon,
                        foregroundColor: Colors.white,
                        padding: EdgeInsets.zero,
                        shape: const CircleBorder(),
                      ),
                      child: _isBotTyping
                          ? const SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            )
                          : const Icon(Icons.arrow_upward_rounded),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // PENDING ATTACHMENT PREVIEW
  // ============================================================

  Widget _pendingAttachmentPreview() {
    final bool isImage = _pendingAttachmentType == 'image';

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(12, 10, 12, 6),
      color: Colors.white,
      child: Align(
        alignment: Alignment.centerLeft,
        child: Stack(
          clipBehavior: Clip.none,
          children: [
            Container(
              constraints: const BoxConstraints(maxWidth: 230),
              padding: const EdgeInsets.all(7),
              decoration: BoxDecoration(
                color: inputBackground,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: borderColor),
              ),
              child: isImage
                  ? ClipRRect(
                      borderRadius: BorderRadius.circular(12),
                      child: Image.file(
                        File(_pendingAttachmentPath!),
                        width: 150,
                        height: 105,
                        fit: BoxFit.cover,
                      ),
                    )
                  : Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(
                          Icons.description_rounded,
                          color: darkMaroon,
                          size: 32,
                        ),

                        const SizedBox(width: 9),

                        Flexible(
                          child: Text(
                            _pendingAttachmentName ?? 'Document',
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: darkText,
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ),
                      ],
                    ),
            ),

            Positioned(
              right: -8,
              top: -8,
              child: GestureDetector(
                onTap: _removePendingAttachment,
                child: Container(
                  width: 25,
                  height: 25,
                  decoration: const BoxDecoration(
                    color: darkMaroon,
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(
                    Icons.close_rounded,
                    color: Colors.white,
                    size: 16,
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ============================================================
  // MESSAGE BUBBLE
  // ============================================================

  Widget _messageBubble({
    required String message,
    required bool isRider,
    required DateTime time,
    String? attachmentPath,
    String? attachmentType,
    String? fileName,
  }) {
    final double maxWidth = MediaQuery.sizeOf(context).width * 0.76;

    final bool hasAttachment =
        attachmentPath != null && attachmentPath.isNotEmpty;

    return Padding(
      padding: const EdgeInsets.only(bottom: 13),
      child: Align(
        alignment: isRider ? Alignment.centerRight : Alignment.centerLeft,
        child: ConstrainedBox(
          constraints: BoxConstraints(maxWidth: maxWidth),
          child: IntrinsicWidth(
            child: Container(
              padding: hasAttachment
                  ? const EdgeInsets.all(6)
                  : const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              decoration: BoxDecoration(
                color: isRider ? darkMaroon : Colors.white,
                borderRadius: BorderRadius.circular(18),
                border: isRider ? null : Border.all(color: borderColor),
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (hasAttachment && attachmentType == 'image')
                    ClipRRect(
                      borderRadius: BorderRadius.circular(13),
                      child: Image.file(
                        File(attachmentPath),
                        width: 235,
                        height: 180,
                        fit: BoxFit.cover,
                      ),
                    ),

                  if (hasAttachment && attachmentType != 'image')
                    Container(
                      constraints: const BoxConstraints(
                        minWidth: 180,
                        maxWidth: 235,
                      ),
                      padding: const EdgeInsets.all(10),
                      decoration: BoxDecoration(
                        color: isRider
                            ? Colors.white.withValues(alpha: 0.12)
                            : cream,
                        borderRadius: BorderRadius.circular(13),
                      ),
                      child: Row(
                        children: [
                          Icon(
                            Icons.description_rounded,
                            color: isRider ? Colors.white : darkMaroon,
                          ),

                          const SizedBox(width: 8),

                          Expanded(
                            child: Text(
                              fileName ?? 'Document',
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: TextStyle(
                                color: isRider ? Colors.white : darkText,
                                fontSize: 12,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),

                  if (message.trim().isNotEmpty) ...[
                    if (hasAttachment) const SizedBox(height: 7),

                    Text(
                      message,
                      style: TextStyle(
                        color: isRider ? Colors.white : darkText,
                        fontSize: 14,
                        height: 1.4,
                      ),
                    ),
                  ],

                  const SizedBox(height: 4),

                  Align(
                    alignment: Alignment.bottomRight,
                    widthFactor: 1,
                    child: Text(
                      _formatTime(time),
                      style: TextStyle(
                        fontSize: 8.5,
                        color: isRider ? Colors.white60 : mutedText,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  // ============================================================
  // TYPING BUBBLE
  // ============================================================

  Widget _typingBubble() {
    return Padding(
      padding: const EdgeInsets.only(bottom: 13),
      child: Align(
        alignment: Alignment.centerLeft,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 11),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: borderColor),
          ),
          child: const Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              SizedBox(
                width: 15,
                height: 15,
                child: CircularProgressIndicator(strokeWidth: 2, color: maroon),
              ),

              SizedBox(width: 8),

              Text(
                'Lumear AI is typing...',
                style: TextStyle(color: brownBeige, fontSize: 11.5),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
