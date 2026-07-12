## Description: <br>
一站式视频解析去水印工具，覆盖抖音、快手、小红书、皮皮虾等短视频平台，帮助代理解析并下载去除平台标识水印的素材。 <br>

This skill is ready for commercial/non-commercial use. <br>

## Publisher: <br>
[kangleizhui](https://clawhub.ai/user/kangleizhui) <br>

### License/Terms of Use: <br>
MIT-0 <br>


## Use Case: <br>
External users and agents use this skill to parse supported short-video links, manage an identity code, download video/image/live-photo assets, and handle paid quota flows when free usage is exhausted. <br>

### Deployment Geography for Use: <br>
Global <br>

## Known Risks and Mitigations: <br>
Risk: A third-party service receives submitted video links, identity codes, payment/order details, and media download requests. <br>
Mitigation: Install only when that data sharing is acceptable, avoid shared or unknown identity codes, and review requests before sending links or payment details. <br>
Risk: The skill includes payment and quota workflows that can create orders or prompt users to purchase additional usage. <br>
Mitigation: Confirm purchase intent before creating orders and verify payment status through the documented status endpoint before telling users that benefits were applied. <br>
Risk: The skill includes remote endpoint fallback behavior and broad media downloading. <br>
Mitigation: Do not rely on automatic endpoint fallback without review, and use downloads only for content the user is authorized to retrieve and redistribute. <br>


## Reference(s): <br>
- [ClawHub skill page](https://clawhub.ai/kangleizhui/skills/duanshipinjiexi) <br>
- [API documentation](references/api-docs.md) <br>
- [Identity code usage rules](references/key-rules.md) <br>
- [Live-photo material handling guide](references/synthesize-guide.md) <br>
- [Troubleshooting guide](references/troubleshooting.md) <br>


## Skill Output: <br>
**Output Type(s):** [text, markdown, shell commands, API calls, configuration, guidance] <br>
**Output Format:** [Markdown guidance with shell commands, API request examples, and downloaded media handling instructions] <br>
**Output Parameters:** [1D] <br>
**Other Properties Related to Output:** [May produce or request downloaded video, image, live-photo, audio, and QR-code media files during agent workflows.] <br>

## Skill Version(s): <br>
1.1.2 (source: server release evidence) <br>

## Ethical Considerations: <br>
Users should evaluate whether this skill is appropriate for their environment, review any generated or modified files before relying on them, and apply their organization's safety, security, and compliance requirements before deployment. <br>
