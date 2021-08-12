import React, { useState } from 'react';

type Props = {
	addRoom: (name: string) => Promise<void>;
	loadRooms: (hidden: boolean) => Promise<void>;
}

const NewRoomForm: React.FC<Props> = (props) => {
	const [name, setName] = useState<string>('');
	const [hidden, setHidden] = useState<boolean>(false);
	const [processing, setProcessing] = useState<boolean>(false);
	const [error, setError] = useState<string>('');
	const [buttonName, setButtonName] = useState<string>(t('bbb', 'Show Outlook Add-In Rooms'));

	function addRoom(ev: React.FormEvent) {
		ev.preventDefault();

		setProcessing(true);
		setError('');

		props.addRoom(name).then(() => {
			setName('');
		}).catch(err => {
			setError(err.toString());
		}).then(() => {
			setProcessing(false);
		});
	}

	function loadRooms(ev: React.FormEvent) {
		ev.preventDefault();

		setHidden(true);
		setProcessing(true);
		setError('');

		props.loadRooms(hidden).then(() => {
			if(buttonName === t('bbb', 'Show Nextcloud-Web Rooms')) {
				setHidden(false);
				setButtonName(t('bbb', 'Show Outlook Add-In Rooms'))
			} else if(buttonName === t('bbb', 'Show Outlook Add-In Rooms')) {
				setHidden(true);
				setButtonName(t('bbb', 'Show Nextcloud-Web Rooms'))
			}
			
		}).catch(err => {
			setError(err.toString());
		}).then(() => {
			setProcessing(false);
		});
	}

	return (
		<form action="#">
			<input
				className="newgroup-name"
				disabled={processing}
				value={name}
				placeholder={t('bbb', 'Room name')}
				onChange={(event) => { setName(event.target.value); }} />

			<button onClick={addRoom}>
				{t('bbb', 'Create')}
			</button>
			<button onClick={loadRooms}>
				{buttonName}
			</button>

			{error && <p>{error}</p>}
		</form>
	);
};

export default NewRoomForm;