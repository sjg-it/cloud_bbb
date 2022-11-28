import axios from '@nextcloud/axios';
import { generateOcsUrl, generateUrl } from '@nextcloud/router';
import { showSuccess, showWarning, showError } from '@nextcloud/dialogs';
import '@nextcloud/dialogs/styles/toast';
import { api } from './Common/Api';
import './filelist.scss';

type OC_Dialogs_Message = (content: string, title: string, dialogType: 'notice' | 'alert' | 'warn' | 'none', buttons?: number, callback?: () => void, modal?: boolean, allowHtml?: boolean) => Promise<void>;
type ExtendedDialogs = typeof OC.dialogs & { message: OC_Dialogs_Message };

const mimeTypes = [
	'application/pdf',
	'application/vnd.oasis.opendocument.presentation',
	'application/vnd.oasis.opendocument.text',
	'application/vnd.oasis.opendocument.spreadsheet',
	'application/vnd.oasis.opendocument.graphics',
	'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
	'application/vnd.openxmlformats-officedocument.presentationml.presentation',
	'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
	'application/msword',
	'application/vnd.ms-powerpoint',
	'application/vnd.ms-excel',
	'image/jpeg',
	'image/png',
	'text/plain',
	'text/rtf',
] as const;

type MimeTypes = typeof mimeTypes[number];

async function createDirectShare(fileId: number): Promise<string> {
	const url = generateOcsUrl('apps/dav/api/v1/', undefined, {
		ocsVersion: 1,
		escape: true,
		noRewrite: true,
	}) + 'direct';
	const createResponse = await axios.post(url, {
		fileId,
	});

	return createResponse.data?.ocs?.data?.url;
}

async function createRoomWithFile(shareUrl: string, filename: string, roomUid: string) {
	const joinUrl = generateUrl('/apps/bbb/b/{uid}?u={url}&filename={filename}', {
		uid: roomUid,
		url: shareUrl,
		filename,
	});

	window.open(joinUrl, '_blank', 'noopener,noreferrer');
}

function insertDocumentToRoom(shareUrl: string, filename: string, roomUid: string) {
	return api.insertDocument(roomUid, shareUrl, filename);
}

async function sendFile(fileId: number, filename: string, roomUid: string) {
	const shareUrl = await createDirectShare(fileId);
	const isRunning = await api.isRunning(roomUid);

	if (isRunning) {
		try {
			const success = await insertDocumentToRoom(shareUrl, filename, roomUid);

			if (success) {
				showSuccess(t('bbb', 'The file "{filename}" was uploaded to your room.', { filename }));
			} else {
				showWarning(t('bbb', 'The file "{filename}" could not be uploaded to your room.', { filename }));
			}
		} catch {
			showError(t('bbb', 'The file "{filename}" could not be uploaded to your room. Maybe your BigBlueButton server does not support this action.', { filename }));
		}
	} else {
		createRoomWithFile(shareUrl, filename, roomUid);
	}
}

async function openDialog(fileId: number, filename: string) {
	const initContent = '<div id="bbb-file-action"><span className="icon icon-loading-small icon-visible"></span></div>';
	const title = t('bbb', 'Send file to BBB');

	await (OC.dialogs as ExtendedDialogs).message(initContent, title, 'none', -1, undefined, true, true);

	const rooms = await api.getRooms();

	const container = $('#bbb-file-action').empty();
	const table = $('<table>').appendTo(container);
	table.attr('style', 'margin-top: 1em; width: 100%;');

	for (const room of rooms) {
		const row = $('<tr>');
		const button = $('<button>');

		button.text(room.running ? t('bbb', 'Send to') : t('bbb', 'Start with'));
		button.addClass(room.running ? 'success' : 'primary');
		button.attr('type', 'button');
		button.on('click', (ev) => {
			ev.preventDefault();

			table.find('button').prop('disabled', true);
			$(ev.target).addClass('icon-loading-small');

			sendFile(fileId, filename, room.uid).then(() => {
				container.parents('.oc-dialog').find('.oc-dialog-close').trigger('click');
			});
		});

		row.append($('<td>').append(button));
		row.append($('<td>').attr('style', 'width: 100%;').text(room.name));
		row.appendTo(table);
	}

	if (rooms.length > 0) {
		const description = t('bbb', 'Please select the room in which you like to use the file "{filename}".', { filename });

		container.append(description);
		container.append(table);
	} else {
		container.append($('p').text(t('bbb', 'No rooms available!')));
	}
}

function registerFileAction(fileActions: any, mime: MimeTypes) {
	fileActions.registerAction({
		name: 'bbb',
		displayName: t('bbb', 'Send to BBB'),
		mime,
		permissions: OC.PERMISSION_SHARE,
		icon: OC.imagePath('bbb', 'app-dark.svg'),
		actionHandler: (fileName, context) => {
			console.log('Action handler');

			openDialog(context.fileInfoModel.id, fileName);
		},
	});
}

const BBBFileListPlugin = {
	ignoreLists: [
		'trashbin',
	],

	attach(fileList) {
		if (this.ignoreLists.includes(fileList.id) || !OC.currentUser) {
			return;
		}

		mimeTypes.forEach(mime => registerFileAction(fileList.fileActions, mime));
	},
};

OC.Plugins.register('OCA.Files.FileList', BBBFileListPlugin);